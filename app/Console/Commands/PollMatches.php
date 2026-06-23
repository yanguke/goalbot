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

        // Send renewal nudge once per match to expired per_match subscribers
        if (in_array($status, ['1H', '2H'], true)) {
            $renewalKey = "renewal_nudge_{$matchId}";
            if (!\Illuminate\Support\Facades\Cache::has($renewalKey)) {
                \Illuminate\Support\Facades\Cache::put($renewalKey, true, now()->addHours(12));
                $expiredSubs = \App\Models\Subscriber::expiredPerMatch($homeTeam, $awayTeam)->get();
                foreach ($expiredSubs as $sub) {
                    $whatsapp->sendText(
                        $sub->phone_number,
                        "🔔 *{$homeTeam} vs {$awayTeam} is LIVE!*\n\nYour match pass has expired — renew to get live goal alerts & AI commentary.\n\nReply *subscribe* to renew now 👇"
                    );
                    usleep(200000);
                }
                if ($expiredSubs->isNotEmpty()) {
                    $this->info("  Sent renewal nudge to {$expiredSubs->count()} expired subscriber(s)");
                }
            }
        }

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
                $score = ($match['goals']['home'] ?? 0) . '-' . ($match['goals']['away'] ?? 0);
                $whatsapp->sendKickoffAlert($sub->phone_number, $kickoffMsg, 'World Cup 2026', $homeTeam, $awayTeam, $score);
                usleep(300000);

                // Referral nudge — once per match per subscriber, right at kickoff
                $refKey = "referral_sent_{$matchId}_{$sub->id}";
                if (!\Illuminate\Support\Facades\Cache::has($refKey)) {
                    \Illuminate\Support\Facades\Cache::put($refKey, true, now()->addHours(12));
                    $referralMsg = "⚽ Enjoying GoalBot live updates?\n\nInvite a friend to follow the World Cup with you!\n\n🌍 *https://goalbot.chat*\n\n_Live goals, commentary & AI insights — free to try._";
                    $whatsapp->sendAlert($sub->phone_number, $referralMsg);
                    usleep(200000);
                }

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

        // Post-match summary — send once after fulltime
        $hasFulltime = collect($events)->contains(fn($e) => $e['type'] === 'fulltime');
        if ($hasFulltime) {
            $summaryKey = "postmatch_sent_{$matchId}";
            if (!\Illuminate\Support\Facades\Cache::has($summaryKey)) {
                \Illuminate\Support\Facades\Cache::put($summaryKey, true, now()->addHours(24));

                $football  = app(FootballDataService::class);
                $allEvents = $football->getMatchEvents($matchId);
                $standings = $football->getStandings();
                $summary   = $ai->generatePostMatchSummary($match, $allEvents, $standings);

                $hGoals = $match['goals']['home'] ?? 0;
                $aGoals = $match['goals']['away'] ?? 0;
                $scoreMsg = "🏁 *FULL TIME*\n\n*{$homeTeam} {$hGoals} — {$aGoals} {$awayTeam}*\n\n";

                // Goals list
                $goalLines = collect($allEvents)
                    ->filter(fn($e) => $e['type'] === 'Goal')
                    ->map(function ($e) {
                        $min    = $e['time']['elapsed'] ?? '?';
                        $player = $e['player']['name'] ?? '?';
                        $team   = $e['team']['name'] ?? '?';
                        $detail = strtolower($e['detail'] ?? '');
                        $tag    = str_contains($detail, 'own') ? ' ⚽(OG)' : (str_contains($detail, 'penalty') ? ' ⚽(pen)' : ' ⚽');
                        return "  {$min}' {$player}{$tag} — {$team}";
                    })->implode("\n");

                if ($goalLines) $scoreMsg .= "Goals:\n{$goalLines}\n";

                foreach ($subscribers as $sub) {
                    $whatsapp->sendAlert($sub->phone_number, $scoreMsg);
                    usleep(150000);
                    if ($summary) {
                        $whatsapp->sendAlert($sub->phone_number, $summary);
                        usleep(150000);
                    }
                }
                $this->info("  Sent post-match summary to {$subscribers->count()} subscribers");
            }
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

        // On first poll: lock all existing entries so we only send future ones.
        $seededCacheKey = "commentary_seeded_{$matchId}";
        if (!\Illuminate\Support\Facades\Cache::has($seededCacheKey)) {
            $allEntries = $commentary->getCommentary($lsSlug);
            $elapsed    = $match['fixture']['status']['elapsed'] ?? 0;
            foreach ($allEntries as $entry) {
                // Parse minute — handle "45+2'" style by taking base + added
                preg_match('/^(\d+)(?:\+(\d+))?/', $entry['time'], $m);
                $entryMin = (int)($m[1] ?? 0) + (int)($m[2] ?? 0);
                // Lock everything up to AND including current elapsed minute
                if ($entryMin <= $elapsed) {
                    $lockKey    = 'c_sent_'   . md5($matchId . $entry['time'] . $entry['text']);
                    $mbmLockKey = 'mbm_sent_' . md5($matchId . $entry['time'] . $entry['text']);
                    \Illuminate\Support\Facades\Cache::put($lockKey, true, now()->addHours(6));
                    \Illuminate\Support\Facades\Cache::put($mbmLockKey, true, now()->addHours(6));
                }
            }
            \Illuminate\Support\Facades\Cache::put($seededCacheKey, true, now()->addHours(6));
            $this->info("  Seeded existing commentary up to {$elapsed}'");
            return;
        }

        // Split subscribers by mode
        $liveSubs   = $subscribers->filter(fn($s) => ($s->commentary_mode ?? 'live') === 'live');
        $digestSubs = $subscribers->filter(fn($s) => ($s->commentary_mode ?? 'live') === 'digest');
        $mbmSubs    = $subscribers->filter(fn($s) => ($s->commentary_mode ?? '') === 'minute_by_minute');

        // Normal run — highlights for live/digest, all entries for mbm
        $highlights  = $commentary->getHighlights($lsSlug, 20);
        $allEntries  = $mbmSubs->isNotEmpty() ? $commentary->getCommentary($lsSlug) : [];
        $newEntries  = [];
        $sent        = 0;

        // Send highlights to live-mode subscribers
        foreach ($highlights as $entry) {
            $lockKey = 'c_sent_' . md5($matchId . $entry['time'] . $entry['text']);

            if (\Illuminate\Support\Facades\Cache::has($lockKey)) continue;
            \Illuminate\Support\Facades\Cache::put($lockKey, true, now()->addHours(6));

            $newEntries[] = $entry;
            $minute = $entry['time'];
            $text   = $entry['text'];
            $msg    = "⚽ *{$minute}* — {$text}";

            foreach ($liveSubs as $sub) {
                // Add frequency control buttons to live messages
                $liveButtons = [
                    ['type' => 'reply', 'reply' => ['id' => 'mode_smart', 'title' => '🎯 Smart Alerts']],
                    ['type' => 'reply', 'reply' => ['id' => 'mode_live', 'title' => '⚡ Live Updates']],
                    ['type' => 'reply', 'reply' => ['id' => 'mode_mbm', 'title' => '🕐 Minute by Minute']],
                ];
                
                $whatsapp->sendInteractiveButtons(
                    $sub->phone_number,
                    "⚽ Live Update",
                    $msg,
                    "Choose update frequency:",
                    $liveButtons
                );
                usleep(100000);
                $sent++;
            }
            if ($liveSubs->isNotEmpty()) {
                $this->info("  Commentary sent: {$minute} — {$text}");
            }
        }

        // Button pool for MBM commentary — pick 3 randomly each entry
        $buttonPool = [
            ['id' => "stats_{$matchId}",    'title' => '📊 Match Stats'],
            ['id' => "lineups_{$matchId}",  'title' => '📋 Lineups'],
            ['id' => "schedule",            'title' => '📅 All Matches'],
            ['id' => "table",               'title' => '🏆 Standings'],
            ['id' => "favorite",            'title' => '⭐ My Team'],
            ['id' => "predict_{$matchId}",  'title' => '🔮 AI Prediction'],
        ];

        // Frequency control buttons (always include)
        $frequencyButtons = [
            ['id' => "mode_smart",          'title' => '🎯 Smart Alerts'],
            ['id' => "mode_live",           'title' => '⚡ Live Updates'],
            ['id' => "mode_mbm",            'title' => '🕐 Minute by Minute'],
        ];

        // Send every entry to minute-by-minute subscribers
        foreach ($allEntries as $entry) {
            $mbmLockKey = 'mbm_sent_' . md5($matchId . $entry['time'] . $entry['text']);

            if (\Illuminate\Support\Facades\Cache::has($mbmLockKey)) continue;
            \Illuminate\Support\Facades\Cache::put($mbmLockKey, true, now()->addHours(6));

            $minute = $entry['time'];
            $text   = $entry['text'];

            // Pick 2 random buttons from pool + 1 frequency button
            $poolKeys    = array_rand($buttonPool, 2);
            $freqKey     = array_rand($frequencyButtons, 1);
            
            $buttons = array_merge(
                array_map(fn($k) => [
                    'type'  => 'reply',
                    'reply' => ['id' => $buttonPool[$k]['id'], 'title' => $buttonPool[$k]['title']],
                ], $poolKeys),
                [[
                    'type'  => 'reply',
                    'reply' => ['id' => $frequencyButtons[$freqKey]['id'], 'title' => $frequencyButtons[$freqKey]['title']],
                ]]
            );

            foreach ($mbmSubs as $sub) {
                $whatsapp->sendInteractiveButtons(
                    $sub->phone_number,
                    "{$homeTeam} vs {$awayTeam}",
                    "🕐 *{$minute}* — {$text}",
                    "Tap for more or keep watching",
                    array_values($buttons)
                );
                usleep(100000);
                $sent++;
            }
            if ($mbmSubs->isNotEmpty()) {
                $this->info("  MBM sent: {$minute} — {$text}");
            }
        }

        // Digest mode: collect entries into a 3-minute bucket, send once per window
        if ($digestSubs->isNotEmpty() && !empty($newEntries)) {
            $elapsed    = $match['fixture']['status']['elapsed'] ?? 0;
            $window     = (int) floor($elapsed / 3) * 3; // e.g. 36 for minutes 36-38
            $digestKey  = "digest_sent_{$matchId}_{$window}";

            if (!\Illuminate\Support\Facades\Cache::has($digestKey)) {
                \Illuminate\Support\Facades\Cache::put($digestKey, true, now()->addMinutes(6));

                $hGoals = $match['goals']['home'] ?? 0;
                $aGoals = $match['goals']['away'] ?? 0;

                // Build raw events string for AI
                $eventLines = collect($newEntries)
                    ->map(fn($e) => "{$e['time']} — {$e['text']}")
                    ->implode("\n");

                // Ask Claude to narrate it as a match story
                $ai      = app(AIMessageGenerator::class);
                $narrative = $ai->generateDigestNarrative(
                    $homeTeam, $awayTeam, $hGoals, $aGoals, $elapsed, $eventLines
                );

                $header    = "📋 *{$homeTeam} {$hGoals}–{$aGoals} {$awayTeam}* | {$elapsed}'\n\n";
                $digestMsg = $header . $narrative;

                foreach ($digestSubs as $sub) {
                    // Add frequency control buttons to digest messages
                    $digestButtons = [
                        ['type' => 'reply', 'reply' => ['id' => 'mode_smart', 'title' => '🎯 Smart Alerts']],
                        ['type' => 'reply', 'reply' => ['id' => 'mode_live', 'title' => '⚡ Live Updates']],
                        ['type' => 'reply', 'reply' => ['id' => 'mode_mbm', 'title' => '🕐 Minute by Minute']],
                    ];
                    
                    $whatsapp->sendInteractiveButtons(
                        $sub->phone_number,
                        "📋 Match Update",
                        $digestMsg,
                        "Choose update frequency:",
                        $digestButtons
                    );
                    usleep(150000);
                    $sent++;
                }
                $this->info("  Digest sent to {$digestSubs->count()} digest subscribers ({$window}' window, " . count($newEntries) . " entries)");
            }
        }

        if ($sent > 0) $this->info("  Sent {$sent} total commentary notifications");
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
