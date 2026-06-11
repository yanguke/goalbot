<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Subscriber;
use App\Services\AIMessageGenerator;
use App\Services\Football\FootballDataService;
use App\Services\MatchEventDetector;
use App\Services\WhatsApp\MessageSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollMatches extends Command
{
    protected $signature = 'matches:poll';
    protected $description = 'Poll live matches and send notifications for events';
    
    private int $notificationCount = 0;
    
    public function handle(
        FootballDataService $football,
        MatchEventDetector $detector,
        AIMessageGenerator $ai,
        MessageSender $whatsapp
    ): int {
        $this->info('Polling matches...');

        $today = now()->format('Y-m-d');

        // Merge today's scheduled matches with live matches endpoint
        // (date endpoint can lag and still show NS even after kickoff)
        $todayMatches = $football->getMatchesForDate($today);
        $liveMatches  = $football->getLiveMatches();

        // Merge, keyed by fixture ID so live version overwrites stale NS version
        $merged = collect($todayMatches)->keyBy(fn($m) => $m['fixture']['id']);
        foreach ($liveMatches as $live) {
            $merged->put($live['fixture']['id'], $live);
        }
        $matches = $merged->values()->toArray();

        if (empty($matches)) {
            $this->warn("No matches found for {$today}");
            return self::SUCCESS;
        }

        $this->info("Found " . count($matches) . " matches for today (" . count($liveMatches) . " live)");
        
        foreach ($matches as $match) {
            $this->processMatch($match, $detector, $ai, $whatsapp);
        }
        
        $this->info("Sent {$this->notificationCount} notifications");
        
        return self::SUCCESS;
    }
    
    private function processMatch(
        array $match,
        MatchEventDetector $detector,
        AIMessageGenerator $ai,
        MessageSender $whatsapp
    ): void {
        $matchId = $match['fixture']['id'];
        $homeTeam = $match['teams']['home']['name'];
        $awayTeam = $match['teams']['away']['name'];
        $status = $match['fixture']['status']['short'] ?? 'unknown';
        
        $this->info("Processing: {$homeTeam} vs {$awayTeam} (Status: {$status})");

        // Send kickoff notification the first time we see 1H — deduped via notifications table
        if ($status === '1H') {
            $subscribers = Subscriber::interestedInMatch($homeTeam, $awayTeam)->get();
            $score = ($match['goals']['home'] ?? 0) . '-' . ($match['goals']['away'] ?? 0);
            $kickoffMsg = "🔴 *KICK OFF!*\n\n{$homeTeam} vs {$awayTeam} has just started!\nScore: {$score}\n\nGoalBot is watching — we'll alert you on every goal, red card & key moment. 👀";
            $sent = 0;
            foreach ($subscribers as $sub) {
                $alreadySent = \App\Models\Notification::where('subscriber_id', $sub->id)
                    ->where('match_id', $matchId)
                    ->where('event_type', 'kickoff')
                    ->exists();
                if ($alreadySent) continue;

                \App\Models\Notification::create([
                    'subscriber_id' => $sub->id,
                    'match_id'      => $matchId,
                    'event_type'    => 'kickoff',
                    'message'       => $kickoffMsg,
                    'sent_at'       => now(),
                    'status'        => 'sent',
                ]);
                $whatsapp->sendAlert($sub->phone_number, $kickoffMsg);
                usleep(100000);
                $sent++;
            }
            if ($sent > 0) $this->info("  Sent kickoff to {$sent} subscribers");
        }

        // Detect events
        $events = $detector->detectEvents($match);
        
        if (empty($events)) {
            $this->line("  No new events");
            return;
        }
        
        $this->info("  Found " . count($events) . " event(s)");
        
        // Get subscribers interested in this match
        $subscribers = Subscriber::interestedInMatch($homeTeam, $awayTeam)->get();
        
        if ($subscribers->isEmpty()) {
            $this->warn("  No subscribers for this match");
            return;
        }
        
        $this->info("  Notifying {$subscribers->count()} subscriber(s)");
        
        foreach ($events as $event) {
            $this->sendEventNotifications($event, $matchId, $subscribers, $ai, $whatsapp);
        }
    }
    
    private function sendEventNotifications(
        array $event,
        int $matchId,
        $subscribers,
        AIMessageGenerator $ai,
        MessageSender $whatsapp
    ): void {
        $eventType = $event['type'];
        $eventData = $event['data'];
        
        $this->line("  Sending {$eventType} notifications...");
        
        foreach ($subscribers as $subscriber) {
            try {
                // Dedup — never send the same event twice to the same subscriber
                $alreadySent = Notification::where('subscriber_id', $subscriber->id)
                    ->where('match_id', $matchId)
                    ->where('event_type', $eventType)
                    ->exists();

                if ($alreadySent) {
                    $this->line("  Skipping {$eventType} for {$subscriber->phone_number} (already sent)");
                    continue;
                }

                // Generate personalized message
                $message = $ai->generate($eventType, $eventData, $subscriber->favorite_team);

                // Record + send atomically
                $notification = Notification::create([
                    'subscriber_id' => $subscriber->id,
                    'match_id'      => $matchId,
                    'event_type'    => $eventType,
                    'message'       => $message,
                    'sent_at'       => now(),
                    'status'        => 'pending',
                ]);

                $success = $whatsapp->sendAlert($subscriber->phone_number, $message);
                $notification->update(['status' => $success ? 'sent' : 'failed']);
                
                if ($success) {
                    $this->notificationCount++;
                }
                
                // Rate limiting - small delay between sends
                usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                Log::error('Failed to send notification', [
                    'subscriber' => $subscriber->id,
                    'event' => $eventType,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
