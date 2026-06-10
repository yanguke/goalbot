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
        
        $this->info("Reminding: {$homeTeam} vs {$awayTeam} at {$kickoff}");
        
        // Find subscribers who care about either team
        $subscribers = Subscriber::where(function ($query) use ($homeTeam, $awayTeam) {
            $query->where('favorite_team', $homeTeam)
                  ->orWhere('favorite_team', $awayTeam);
        })->where('notifications_enabled', true)->get();
        
        if ($subscribers->isEmpty()) {
            $this->warn("  No subscribers interested in this match");
            return;
        }
        
        $this->info("  Sending to {$subscribers->count()} subscriber(s)");
        
        // Generate reminder message
        $message = $ai->generateReminder($match);
        
        foreach ($subscribers as $subscriber) {
            try {
                // Add personalization for their team
                if ($subscriber->favorite_team) {
                    $personalizedMessage = $this->personalizeReminder($message, $subscriber->favorite_team, $homeTeam, $awayTeam);
                } else {
                    $personalizedMessage = $message;
                }
                
                $success = $whatsapp->sendAlert($subscriber->phone_number, $personalizedMessage);
                
                if ($success) {
                    $this->reminderCount++;
                    
                    // Update last notification time
                    $subscriber->update(['last_notification_at' => now()]);
                }
                
                // Rate limiting
                usleep(100000);
                
            } catch (\Exception $e) {
                Log::error('Failed to send reminder', [
                    'subscriber' => $subscriber->id,
                    'match' => "{$homeTeam} vs {$awayTeam}",
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
