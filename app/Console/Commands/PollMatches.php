<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Subscriber;
use App\Services\AIMessageGenerator;
use App\Services\Football\FootballDataService;
use App\Services\Football\LiveScoreCommentaryService;
use App\Services\MatchEventDetector;
use App\Services\WhatsApp\MessageSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollMatches extends Command
{
    protected $signature   = 'matches:poll {--interval=15 : Seconds between polls within the cron minute}';
    protected $description = 'Poll live matches and send notifications for events';
    
    private int $notificationCount = 0;
    
    public function handle(
        FootballDataService $football,
        MatchEventDetector $detector,
        AIMessageGenerator $ai,
        MessageSender $whatsapp
    ): int {
        $interval  = max(10, (int) $this->option('interval')); // minimum 10s
        $duration  = 58; // run for 58s so we finish before next cron tick
        $startedAt = time();

        $this->info("Polling every {$interval}s for {$duration}s...");

        do {
            $this->runOnce($football, $detector, $ai, $whatsapp);

            $elapsed   = time() - $startedAt;
            $remaining = $duration - $elapsed;

            if ($remaining > $interval) {
                $this->info("  Sleeping {$interval}s (elapsed {$elapsed}s)...");
                sleep($interval);
            } else {
                break;
            }
        } while (true);

        $this->info("Done. Sent {$this->notificationCount} total notifications.");
        return self::SUCCESS;
    }

    private function runOnce(
        FootballDataService $football,
        MatchEventDetector $detector,
        AIMessageGenerator $ai,
        MessageSender $whatsapp
    ): void {
        $today = now()->format('Y-m-d');

        // Merge today's scheduled matches with live matches endpoint
        $todayMatches = $football->getMatchesForDate($today);
        $liveMatches  = $football->getLiveMatches();

        $merged = collect($todayMatches)->keyBy(fn($m) => $m['fixture']['id']);
        foreach ($liveMatches as $live) {
            $merged->put($live['fixture']['id'], $live);
        }
        $matches = $merged->values()->toArray();

        if (empty($matches)) {
            $this->warn("No matches found for {$today}");
            return;
        }

        $this->info("Found " . count($matches) . " matches (" . count($liveMatches) . " live) @ " . now()->format('H:i:s'));

        foreach ($matches as $match) {
            $this->processMatch($match, $detector, $ai, $whatsapp);
        }
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

        // Poll notable LiveScore commentary entries and send once per entry
        $this->processCommentary($match, $whatsapp);

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
    
    private function processCommentary(array $match, MessageSender $whatsapp): void
    {
        $matchId  = $match['fixture']['id'];
        $homeTeam = $match['teams']['home']['name'];
        $awayTeam = $match['teams']['away']['name'];
        $status   = $match['fixture']['status']['short'] ?? '';

        // Only during live play
        if (!in_array($status, ['1H', '2H', 'ET'], true)) return;

        $football = app(FootballDataService::class);
        $lsSlug   = $football->getLiveScoreFullSlug($homeTeam, $awayTeam, $matchId);
        if (!$lsSlug) return;

        $commentary  = app(LiveScoreCommentaryService::class);
        $subscribers = Subscriber::interestedInMatch($homeTeam, $awayTeam)->get();
        if ($subscribers->isEmpty()) return;

        // On first poll: mark ALL current entries as seen (cache-based, no DB write)
        // so we never retroactively send old commentary.
        $seededCacheKey = "commentary_seeded_{$matchId}";
        if (!\Illuminate\Support\Facades\Cache::has($seededCacheKey)) {
            $allEntries = $commentary->getCommentary($lsSlug);
            foreach ($allEntries as $entry) {
                $lockKey = 'c_sent_' . md5($matchId . $entry['time'] . $entry['text']);
                \Illuminate\Support\Facades\Cache::put($lockKey, true, now()->addHours(6));
            }
            \Illuminate\Support\Facades\Cache::put($seededCacheKey, true, now()->addHours(6));
            $this->info("  Seeded " . count($allEntries) . " existing commentary entries");
            return;
        }

        // Normal run — send only highlights not yet locked
        $highlights = $commentary->getHighlights($lsSlug, 20);
        $sent = 0;

        foreach ($highlights as $entry) {
            $lockKey = 'c_sent_' . md5($matchId . $entry['time'] . $entry['text']);

            // Atomic: only proceed if we can set the lock (wasn't already sent)
            if (\Illuminate\Support\Facades\Cache::has($lockKey)) continue;
            \Illuminate\Support\Facades\Cache::put($lockKey, true, now()->addHours(6));

            $minute = $entry['time'];
            $text   = $entry['text'];
            $msg    = "⚽ *{$minute}* — {$text}";

            foreach ($subscribers as $sub) {
                $whatsapp->sendAlert($sub->phone_number, $msg);
                usleep(100000);
                $sent++;
            }
            $this->info("  Commentary sent: {$minute} — {$text}");
        }

        if ($sent > 0) $this->info("  Sent {$sent} commentary notifications");
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
