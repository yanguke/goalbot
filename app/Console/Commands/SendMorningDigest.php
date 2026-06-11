<?php

namespace App\Console\Commands;

use App\Models\Subscriber;
use App\Services\Football\FootballDataService;
use App\Services\WhatsApp\MessageSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMorningDigest extends Command
{
    protected $signature = 'digest:morning {--force : Send even if no matches today}';
    protected $description = 'Send daily morning digest to all active subscribers (8 AM EAT)';

    public function handle(FootballDataService $football, MessageSender $whatsapp): int
    {
        $today = now()->setTimezone('Africa/Nairobi')->toDateString();
        $matches = $football->getMatchesForDate(
            Carbon::parse($today, 'Africa/Nairobi')->utc()->toDateString()
        );

        // Filter to NS (not started) and upcoming
        $upcoming = collect($matches)->filter(fn($m) =>
            in_array($m['fixture']['status']['short'] ?? '', ['NS', 'TBD'], true)
        )->sortBy('fixture.date')->values();

        if ($upcoming->isEmpty() && !$this->option('force')) {
            $this->info('No matches today — skipping digest');
            return self::SUCCESS;
        }

        $subscribers = Subscriber::where('notifications_enabled', true)
            ->where('is_active', true)
            ->get();

        if ($subscribers->isEmpty()) {
            $this->info('No active subscribers');
            return self::SUCCESS;
        }

        $message = $this->buildMessage($upcoming->toArray(), $today);
        $this->info("Sending digest to {$subscribers->count()} subscribers...");

        $sent = 0;
        foreach ($subscribers as $subscriber) {
            try {
                if ($whatsapp->sendAlert($subscriber->phone_number, $message)) {
                    $sent++;
                }
                usleep(150000);
            } catch (\Exception $e) {
                Log::error('Digest send failed', ['subscriber' => $subscriber->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Digest sent to {$sent} subscribers");
        return self::SUCCESS;
    }

    private function buildMessage(array $matches, string $today): string
    {
        $date = Carbon::parse($today)->format('l, d M Y');
        $msg = "🌅 *Good morning! World Cup 2026*\n📅 {$date}\n\n";

        if (empty($matches)) {
            $msg .= "😴 No matches scheduled for today — rest day!\n\n";
            $msg .= "💡 Use these commands:\n";
        } else {
            $count = count($matches);
            $msg .= "⚽ *{$count} match" . ($count > 1 ? 'es' : '') . " today:*\n\n";
            foreach ($matches as $m) {
                $home = $m['teams']['home']['name'];
                $away = $m['teams']['away']['name'];
                $dt = Carbon::parse($m['fixture']['date'])->setTimezone('Africa/Nairobi');
                $round = $m['league']['round'] ?? '';
                $msg .= "🏟 *{$home} vs {$away}*\n";
                $msg .= "   ⏰ {$dt->format('H:i')} EAT | {$round}\n\n";
            }
            $msg .= "💡 Commands:\n";
        }

        $msg .= "• *results* — live scores\n";
        $msg .= "• *table* — group standings\n";
        $msg .= "• *next [team]* — team's next game\n";
        $msg .= "• *predict [team A] vs [team B]* — AI prediction\n\n";
        $msg .= "_GoalBot — your World Cup companion_ ⚽🏆";

        return $msg;
    }
}
