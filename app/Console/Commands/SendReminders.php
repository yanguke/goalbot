<?php

namespace App\Console\Commands;

use App\Models\Subscriber;
use App\Services\AIMessageGenerator;
use App\Services\Football\FootballDataService;
use App\Services\WhatsApp\MessageSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send match reminders to subscribers';
    
    private int $reminderCount = 0;
    
    public function handle(
        FootballDataService $football,
        AIMessageGenerator $ai,
        MessageSender $whatsapp
    ): int {
        $this->info('Checking for upcoming matches...');

        // Two reminder windows only:
        //   2 hours — full pre-match intelligence briefing
        //   10 minutes — short sharp kickoff countdown
        $windows = [
            ['label' => '2 hours',    'from' => now()->addMinutes(115), 'to' => now()->addMinutes(125)],
            ['label' => '10 minutes', 'from' => now()->addMinutes(10),  'to' => now()->addMinutes(16)],
        ];

        $anyFound = false;
        foreach ($windows as $window) {
            $found = $football->getMatchesStartingBetween(
                $window['from']->toIso8601String(),
                $window['to']->toIso8601String()
            );
            if (empty($found)) continue;

            foreach ($found as $match) {
                $matchId = $match['fixture']['id'];
                $cacheKey = "reminder_{$matchId}_{$window['label']}";
                if (\Cache::has($cacheKey)) continue; // already sent this milestone
                \Cache::put($cacheKey, true, now()->addHours(6));

                $anyFound = true;
                $this->info("Match in ~{$window['label']}: {$match['teams']['home']['name']} vs {$match['teams']['away']['name']}");
                $this->sendRemindersForMatch($match, $ai, $whatsapp, $window['label']);
            }
        }

        if (!$anyFound) {
            $this->warn("No upcoming matches in any reminder window");
            return self::SUCCESS;
        }
        
        $this->info("Sent {$this->reminderCount} reminders");
        
        return self::SUCCESS;
    }
    
    private function sendRemindersForMatch(
        array $match,
        AIMessageGenerator $ai,
        MessageSender $whatsapp,
        string $windowLabel = '1 hour'
    ): void {
        $homeTeam = $match['teams']['home']['name'];
        $awayTeam = $match['teams']['away']['name'];
        $kickoff = $match['fixture']['date'];
        $venue = $match['fixture']['venue']['name'] ?? 'TBD';
        $round = $match['league']['round'] ?? 'World Cup 2026';

        $this->info("Reminding: {$homeTeam} vs {$awayTeam} at {$kickoff}");

        // All active subscribers get reminders for WC matches
        $subscribers = Subscriber::where('notifications_enabled', true)
            ->where('is_active', true)
            ->get();
        
        if ($subscribers->isEmpty()) {
            $this->warn("  No subscribers");
            return;
        }
        
        $this->info("  Sending to {$subscribers->count()} subscriber(s)");

        // Generate messages — each window sends different content, no cross-window duplication
        $football  = app(FootballDataService::class);
        $fixtureId = $match['fixture']['id'];

        // Reminder tone is window-specific ("5 minutes" vs "2 hours")
        $reminder = $ai->generateReminder($match, $windowLabel);

        // Full intelligence briefing at 2hr only — prediction, odds, H2H, form
        $briefing = ($windowLabel === '2 hours') ? $ai->generatePreMatchBriefing($match) : null;

        // No separate prediction/odds message — all baked into the briefing
        $prediction = null;
        $oddsMsg    = null;
        
        foreach ($subscribers as $subscriber) {
            try {
                $base = $subscriber->favorite_team
                    ? $this->personalizeReminder($reminder, $subscriber->favorite_team, $homeTeam, $awayTeam)
                    : $reminder;

                // 10-minute window: tight urgency header only, no extra messages
                $urgencyPrefix = ($windowLabel === '10 minutes')
                    ? "⏰ *{$homeTeam} vs {$awayTeam} kicks off in 10 minutes!*\n\n"
                    : '';
                $personalizedReminder = $urgencyPrefix . $base;

                // Determine if user is outside the 24h session window
                $outsideWindow = $subscriber->window_failed
                    || is_null($subscriber->last_message_in_at)
                    || $subscriber->last_message_in_at->lt(now()->subHours(24));

                if ($outsideWindow) {
                    // Use approved template to re-open the window
                    // minutes label e.g. "15 minutes" -> "15" for {{4}}
                    $minutesOnly = preg_replace('/[^0-9]/', '', $windowLabel) ?: $windowLabel;
                    $sent = $whatsapp->sendTemplate(
                        $subscriber->phone_number,
                        'match_reminder_alert',
                        ['FIFA World Cup 2026', $homeTeam, $awayTeam, $minutesOnly, $venue]
                    );
                    Log::info('Reminder via template (out-of-window)', [
                        'phone'  => $subscriber->phone_number,
                        'match'  => "{$homeTeam} vs {$awayTeam}",
                        'window' => $windowLabel,
                        'sent'   => $sent,
                    ]);
                } else {
                    // User is in-window — send rich plain text
                    $whatsapp->sendReminderAlert(
                        $subscriber->phone_number,
                        $personalizedReminder,
                        'FIFA World Cup 2026',
                        $homeTeam,
                        $awayTeam,
                        $windowLabel,
                        $venue
                    );
                }
                usleep(200000);

                // Send prediction after brief delay
                if ($prediction) {
                    $whatsapp->sendAlert($subscriber->phone_number, $prediction);
                    usleep(200000);
                }

                // Odds are now embedded in the briefing — no separate message

                // Send intelligence briefing at 2hr window
                if ($briefing) {
                    $whatsapp->sendAlert($subscriber->phone_number, $briefing);
                    usleep(200000);
                }

                $this->reminderCount++;
                $subscriber->update(['last_notification_at' => now()]);

            } catch (\Exception $e) {
                Log::error('Failed to send reminder', [
                    'subscriber' => $subscriber->id,
                    'match' => "{$homeTeam} vs {$awayTeam}",
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildOddsMessage(FootballDataService $football, int $fixtureId, string $home, string $away): ?string
    {
        $odds        = $football->getOdds($fixtureId);
        $predictions = $football->getPredictions($fixtureId);
        $injuries    = $football->getInjuries($fixtureId);

        $lines = [];

        // Odds
        $matchWinner = $odds['bets']['Match Winner'] ?? [];
        if (!empty($matchWinner)) {
            $hw = $matchWinner['Home'] ?? '?';
            $d  = $matchWinner['Draw'] ?? '?';
            $aw = $matchWinner['Away'] ?? '?';
            $lines[] = "📊 *Odds* ({$odds['bookmaker']}): {$home} {$hw} | Draw {$d} | {$away} {$aw}";
        }

        // Predictions
        if ($predictions) {
            $pct    = $predictions['predictions']['percent'] ?? [];
            $advice = $predictions['predictions']['advice'] ?? null;
            $winner = $predictions['predictions']['winner']['name'] ?? null;
            if ($winner && $winner !== 'null') {
                $lines[] = "🔮 Predicted winner: *{$winner}*";
            }
            if ($advice && $advice !== 'No predictions available') {
                $lines[] = "💡 {$advice}";
            }
            if (!empty($pct)) {
                $lines[] = "Win probability: {$home} {$pct['home']} | Draw {$pct['draw']} | {$away} {$pct['away']}";
            }
        }

        // Injuries
        if (!empty($injuries)) {
            $lines[] = "🚑 *Injury updates:*";
            foreach ($injuries as $inj) {
                $pName  = $inj['player']['name'] ?? '?';
                $type   = $inj['player']['type'] ?? '?';
                $tName  = $inj['team']['name'] ?? '?';
                $lines[] = "  • {$tName}: {$pName} ({$type})";
            }
        }

        return !empty($lines) ? implode("\n", $lines) : null;
    }

    private function generatePrediction(string $home, string $away, string $venue, string $round): ?string
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) return null;

        $cacheKey = 'predict_' . md5("{$home}_{$away}");
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 7200, function () use ($home, $away, $venue, $round, $apiKey) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(15)->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model', 'claude-haiku-4-5'),
                    'max_tokens' => 300,
                    'system' => 'You are GoalBot. Write a pre-match prediction for a World Cup game. Keep it under 500 characters, use 2-3 emojis, be bold with a predicted scoreline.',
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Predict: {$home} vs {$away} — {$round} at {$venue}. Include: key players to watch, predicted score, and one bold take.",
                    ]],
                    'temperature' => 0.9,
                ]);
                return $response->successful()
                    ? "🔮 *GoalBot Prediction*\n\n" . trim($response->json('content.0.text', ''))
                    : null;
            } catch (\Exception $e) {
                Log::warning('Prediction failed', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }
    
    private function personalizeReminder(string $message, string $favoriteTeam, string $homeTeam, string $awayTeam): string
    {
        // Add team-specific excitement
        if ($favoriteTeam === $homeTeam || $favoriteTeam === $awayTeam) {
            $enthusiasm = match(rand(1, 3)) {
                1 => "🔥 Your team {$favoriteTeam} plays in 2 hours! ",
                2 => "⚽ It's match day for {$favoriteTeam}! ",
                3 => "🏆 Time to support {$favoriteTeam}! ",
            };
            
            return $enthusiasm . $message;
        }
        
        return $message;
    }
}
