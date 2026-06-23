<?php

namespace App\Services\Metrics;

use App\Models\Subscriber;
use App\Models\LandingVisit;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetricsReportingService
{
    private string $apiUrl = 'https://niaje.helaplus.com/client/sendMessage/DCBA';
    private string $apiKey = 'helaplus';
    private string $chatId = '120363426674587893@g.us';

    public function __construct()
    {
        // Using provided API key
    }

    /**
     * Send hourly metrics report to monitoring group
     */
    public function sendHourlyReport(): bool
    {
        try {
            $metrics = $this->getHourlyMetrics();
            $message = $this->formatHourlyMessage($metrics);
            
            return $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Failed to send hourly metrics report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send daily metrics report to monitoring group
     */
    public function sendDailyReport(): bool
    {
        try {
            $metrics = $this->getDailyMetrics();
            $message = $this->formatDailyMessage($metrics);
            
            return $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Failed to send daily metrics report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send weekly metrics report to monitoring group
     */
    public function sendWeeklyReport(): bool
    {
        try {
            $metrics = $this->getWeeklyMetrics();
            $message = $this->formatWeeklyMessage($metrics);
            
            return $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Failed to send weekly metrics report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send critical alert to monitoring group
     */
    public function sendAlert(string $message): bool
    {
        try {
            $alertMessage = "🚨 **ALERT** 🚨\n\n" . $message;
            return $this->sendMessage($alertMessage);
        } catch (\Exception $e) {
            Log::error('Failed to send alert', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            return false;
        }
    }

    /**
     * Get hourly platform metrics
     */
    private function getHourlyMetrics(): array
    {
        $hourStart = now()->startOfHour();
        $previousHour = now()->subHour()->startOfHour();
        $gmtPlus3 = now()->timezone('Africa/Nairobi');

        return [
            'hour' => $gmtPlus3->format('H:00') . ' GMT+3',
            'new_subscribers_hour' => Subscriber::where('created_at', '>=', $hourStart)->count(),
            'new_subscribers_prev_hour' => Subscriber::whereBetween('created_at', [$previousHour, $hourStart])->count(),
            'total_subscribers' => Subscriber::count(),
            'active_subscribers' => Subscriber::where('notifications_enabled', true)->count(),
            'new_leads_hour' => Lead::where('created_at', '>=', $hourStart)->count(),
            'landing_visits_hour' => LandingVisit::where('event', 'view')->where('created_at', '>=', $hourStart)->count(),
            'messages_sent_hour' => $this->getMessagesSent($hourStart),
            'revenue_hour' => $this->getRevenue($hourStart),
            'api_response_time' => $this->getApiResponseTime(),
            'server_uptime' => $this->getServerUptime(),
            'live_matches_active' => $this->getLiveMatchesCount(),
            'queue_size' => $this->getQueueSize(),
        ];
    }

    /**
     * Get daily platform metrics
     */
    private function getDailyMetrics(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        return [
            'new_subscribers' => Subscriber::where('created_at', '>=', $today)->count(),
            'total_subscribers' => Subscriber::count(),
            'active_subscribers' => Subscriber::where('notifications_enabled', true)->count(),
            'paid_subscribers' => Subscriber::whereNotNull('paid_at')->count(),
            'new_leads' => Lead::where('created_at', '>=', $today)->count(),
            'landing_visits' => LandingVisit::where('event', 'view')->where('created_at', '>=', $today)->count(),
            'conversion_rate' => $this->calculateConversionRate($today),
            'messages_sent' => $this->getMessagesSent($today),
            'revenue_today' => $this->getRevenue($today),
            'server_uptime' => $this->getServerUptime(),
            'api_response_time' => $this->getApiResponseTime(),
        ];
    }

    /**
     * Get weekly platform metrics
     */
    private function getWeeklyMetrics(): array
    {
        $weekStart = now()->startOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();

        return [
            'new_subscribers_week' => Subscriber::where('created_at', '>=', $weekStart)->count(),
            'new_subscribers_last_week' => Subscriber::whereBetween('created_at', [$lastWeekStart, $weekStart])->count(),
            'total_subscribers' => Subscriber::count(),
            'active_subscribers' => Subscriber::where('notifications_enabled', true)->count(),
            'paid_subscribers' => Subscriber::whereNotNull('paid_at')->count(),
            'weekly_revenue' => $this->getRevenue($weekStart),
            'avg_daily_revenue' => $this->getRevenue($weekStart) / 7,
            'top_countries' => $this->getTopCountries(),
            'commentary_engagement' => $this->getCommentaryEngagement($weekStart),
            'churn_rate' => $this->getChurnRate($weekStart),
            'viral_coefficient' => $this->getViralCoefficient($weekStart),
        ];
    }

    /**
     * Format hourly metrics message
     */
    private function formatHourlyMessage(array $metrics): string
    {
        $message = "⏰ **Hourly Metrics - {$metrics['hour']}**\n\n";
        
        $message .= "👥 **Growth**\n";
        $diff = $metrics['new_subscribers_hour'] - $metrics['new_subscribers_prev_hour'];
        $trendIcon = $diff >= 0 ? '📈' : '📉';
        $message .= "• New subs: {$metrics['new_subscribers_hour']} {$trendIcon} " . ($diff >= 0 ? '+' : '') . $diff . "\n";
        $message .= "• Total: " . number_format($metrics['total_subscribers']) . "\n";
        $message .= "• Active: " . number_format($metrics['active_subscribers']) . "\n";
        $message .= "• New leads: {$metrics['new_leads_hour']}\n\n";
        
        $message .= "📈 **Activity**\n";
        $message .= "• Landing visits: {$metrics['landing_visits_hour']}\n";
        $message .= "• Messages sent: " . number_format($metrics['messages_sent_hour']) . "\n";
        $message .= "• Revenue: KES " . number_format($metrics['revenue_hour']) . "\n\n";
        
        $message .= "⚡ **System**\n";
        $message .= "• Live matches: {$metrics['live_matches_active']}\n";
        $message .= "• Queue size: {$metrics['queue_size']}\n";
        $message .= "• API response: {$metrics['api_response_time']}ms\n\n";
        
        // Add alerts if needed
        if ($metrics['api_response_time'] > 1000) {
            $message .= "⚠️ **Warning**: Slow API response time\n";
        }
        if ($metrics['queue_size'] > 100) {
            $message .= "⚠️ **Warning**: High queue size\n";
        }
        
        return $message;
    }

    /**
     * Format daily metrics message
     */
    private function formatDailyMessage(array $metrics): string
    {
        $message = "📊 **Daily Metrics Report - " . now()->timezone('Africa/Nairobi')->format('M j, Y') . " (GMT+3)**\n\n";
        
        $message .= "👥 **Growth**\n";
        $message .= "• New subscribers: {$metrics['new_subscribers']}\n";
        $message .= "• Total subscribers: " . number_format($metrics['total_subscribers']) . "\n";
        $message .= "• Active subscribers: " . number_format($metrics['active_subscribers']) . "\n";
        $message .= "• Paid subscribers: " . number_format($metrics['paid_subscribers']) . "\n";
        $message .= "• New leads: {$metrics['new_leads']}\n\n";
        
        $message .= "📈 **Engagement**\n";
        $message .= "• Landing visits: {$metrics['landing_visits']}\n";
        $message .= "• Conversion rate: " . number_format($metrics['conversion_rate'], 2) . "%\n";
        $message .= "• Messages sent: " . number_format($metrics['messages_sent']) . "\n\n";
        
        $message .= "💰 **Revenue**\n";
        $message .= "• Today's revenue: KES " . number_format($metrics['revenue_today']) . "\n\n";
        
        $message .= "⚡ **Performance**\n";
        $message .= "• Server uptime: {$metrics['server_uptime']}\n";
        $message .= "• API response time: {$metrics['api_response_time']}ms\n\n";
        
        // Add trends
        $message .= $this->getDailyTrends();
        
        return $message;
    }

    /**
     * Format weekly metrics message
     */
    private function formatWeeklyMessage(array $metrics): string
    {
        $message = "📈 **Weekly Metrics Report - " . now()->timezone('Africa/Nairobi')->format('M j, Y') . " (GMT+3)**\n\n";
        
        $message .= "👥 **Growth**\n";
        $growth = $metrics['new_subscribers_week'] - $metrics['new_subscribers_last_week'];
        $growthIcon = $growth >= 0 ? '📈' : '📉';
        $message .= "• New subscribers: {$metrics['new_subscribers_week']} {$growthIcon} " . ($growth >= 0 ? '+' : '') . $growth . "\n";
        $message .= "• Total subscribers: " . number_format($metrics['total_subscribers']) . "\n";
        $message .= "• Paid subscribers: " . number_format($metrics['paid_subscribers']) . "\n\n";
        
        $message .= "💰 **Revenue**\n";
        $message .= "• Weekly revenue: KES " . number_format($metrics['weekly_revenue']) . "\n";
        $message .= "• Avg daily: KES " . number_format($metrics['avg_daily_revenue']) . "\n\n";
        
        $message .= "🌍 **Geography**\n";
        foreach ($metrics['top_countries'] as $country => $count) {
            $message .= "• {$country}: {$count}\n";
        }
        $message .= "\n";
        
        $message .= "📊 **Engagement**\n";
        $message .= "• Commentary engagement: " . number_format($metrics['commentary_engagement'], 1) . "%\n";
        $message .= "• Churn rate: " . number_format($metrics['churn_rate'], 1) . "%\n";
        $message .= "• Viral coefficient: " . number_format($metrics['viral_coefficient'], 2) . "\n\n";
        
        $message .= "🎯 **Goals**\n";
        $message .= $this->getWeeklyGoalsProgress($metrics);
        
        return $message;
    }

    /**
     * Send message via Helaplus API
     */
    private function sendMessage(string $message): bool
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                ])
                ->post($this->apiUrl, [
                    'chatId' => $this->chatId,
                    'content' => $message,
                    'contentType' => 'string'
                ]);

            if ($response->successful()) {
                Log::info('Metrics report sent to monitoring group via Helaplus', [
                    'message_length' => strlen($message),
                    'chat_id' => $this->chatId,
                    'status' => $response->status()
                ]);
                return true;
            } else {
                Log::error('Failed to send metrics report via Helaplus', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'chat_id' => $this->chatId
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception sending metrics report via Helaplus', [
                'error' => $e->getMessage(),
                'chat_id' => $this->chatId
            ]);
            return false;
        }
    }

    /**
     * Calculate conversion rate
     */
    private function calculateConversionRate($date): float
    {
        $visits = LandingVisit::where('event', 'view')
            ->where('created_at', '>=', $date)
            ->count();
        
        $subscribers = Subscriber::where('created_at', '>=', $date)
            ->count();
        
        return $visits > 0 ? ($subscribers / $visits) * 100 : 0;
    }

    /**
     * Get messages sent count
     */
    private function getMessagesSent($date): int
    {
        // This would need to be implemented based on your message logging
        // For now, return a placeholder
        return 0;
    }

    /**
     * Get revenue for date range
     */
    private function getRevenue($date): int
    {
        // This would need to be implemented based on your payment tracking
        // For now, return a placeholder
        return 0;
    }

    /**
     * Get server uptime
     */
    private function getServerUptime(): string
    {
        // Simple uptime check - can be enhanced
        $uptime = shell_exec('uptime');
        return trim($uptime) ?: 'Unknown';
    }

    /**
     * Get live matches count
     */
    private function getLiveMatchesCount(): int
    {
        // This would need to be implemented based on your match tracking
        // For now, return a placeholder or check your database
        try {
            // Example: Check for matches that are currently live
            return DB::table('matches')
                ->where('status', 'live')
                ->orWhere(function($query) {
                    $query->where('kickoff_time', '<=', now())
                          ->where('end_time', '>', now());
                })
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get queue size (for message processing)
     */
    private function getQueueSize(): int
    {
        try {
            // Check Redis queue size if using Redis
            if (app()->bound('redis')) {
                return app('redis')->llen('queues:default');
            }
            
            // Or check database queue if using database queue driver
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get API response time
     */
    private function getApiResponseTime(): int
    {
        $start = microtime(true);
        
        // Test API endpoint
        Http::timeout(5)->get(config('app.url') . '/api/health');
        
        return round((microtime(true) - $start) * 1000);
    }

    /**
     * Get top countries by subscribers
     */
    private function getTopCountries(): array
    {
        return Subscriber::select('country', DB::raw('count(*) as count'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'country')
            ->toArray();
    }

    /**
     * Get commentary engagement rate
     */
    private function getCommentaryEngagement($date): float
    {
        // This would need to be implemented based on your engagement tracking
        // For now, return a placeholder
        return 75.5;
    }

    /**
     * Get churn rate for period
     */
    private function getChurnRate($date): float
    {
        // This would need to be implemented based on your churn tracking
        // For now, return a placeholder
        return 5.2;
    }

    /**
     * Get viral coefficient
     */
    private function getViralCoefficient($date): float
    {
        // This would need to be implemented based on your referral tracking
        // For now, return a placeholder
        return 0.3;
    }

    /**
     * Get daily trends
     */
    private function getDailyTrends(): string
    {
        $trends = "📊 **Trends**\n";
        
        // Compare with yesterday
        $yesterday = now()->subDay()->startOfDay();
        $todayNewSubs = Subscriber::where('created_at', '>=', now()->startOfDay())->count();
        $yesterdayNewSubs = Subscriber::whereBetween('created_at', [$yesterday, now()->startOfDay()])->count();
        
        $diff = $todayNewSubs - $yesterdayNewSubs;
        $trendIcon = $diff >= 0 ? '📈' : '📉';
        $trends .= "• New subs trend: {$trendIcon} " . ($diff >= 0 ? '+' : '') . $diff . " vs yesterday\n";
        
        return $trends . "\n";
    }

    /**
     * Get weekly goals progress
     */
    private function getWeeklyGoalsProgress(array $metrics): string
    {
        $goals = "• New subs: {$metrics['new_subscribers_week']}/100 🎯\n";
        $goals .= "• Revenue: KES " . number_format($metrics['weekly_revenue']) . "/50,000 💰\n";
        $goals .= "• Engagement: " . number_format($metrics['commentary_engagement'], 1) . "%/80% 📊\n";
        
        return $goals;
    }
}
