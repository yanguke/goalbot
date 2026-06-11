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
        
        // Check for matches starting in exactly 2 hours
        $windowStart = now()->addHours(2)->startOfMinute();
        $windowEnd = now()->addHours(2)->addMinutes(5)->startOfMinute();
        
        $matches = $football->getMatchesStartingBetween(
            $windowStart->toIso8601String(),
            $windowEnd->toIso8601String()
        );
        
        if (empty($matches)) {
            $this->warn("No matches starting in 2 hours");
            return self::SUCCESS;
        }
        
        $this->info("Found " . count($matches) . " match(es) starting in ~2 hours");
        
        foreach ($matches as $match) {
            $this->sendRemindersForMatch($match, $ai, $whatsapp);
        }
        
        $this->info("Sent {$this->reminderCount} reminders");
        
        return self::SUCCESS;
    }
    
    private function sendRemindersForMatch(
        array $match,
        AIMessageGenerator $ai,
        MessageSender $whatsapp
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

        // Generate reminder + prediction messages (shared across all subscribers)
        $reminder = $ai->generateReminder($match);
        $prediction = $this->generatePrediction($homeTeam, $awayTeam, $venue, $round);
        
        foreach ($subscribers as $subscriber) {
            try {
                $personalizedReminder = $subscriber->favorite_team
                    ? $this->personalizeReminder($reminder, $subscriber->favorite_team, $homeTeam, $awayTeam)
                    : $reminder;

                $whatsapp->sendAlert($subscriber->phone_number, $personalizedReminder);
                usleep(200000);

                // Send prediction after brief delay
                if ($prediction) {
                    $whatsapp->sendAlert($subscriber->phone_number, $prediction);
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
