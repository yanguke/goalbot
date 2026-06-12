<?php

namespace App\Console\Commands;

use App\Models\Subscriber;
use App\Services\Football\FootballDataService;
use App\Services\WhatsApp\MessageSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DigestMorning extends Command
{
    protected $signature   = 'digest:morning';
    protected $description = 'Send morning match day digest to all active subscribers';

    public function handle(FootballDataService $football, MessageSender $whatsapp): int
    {
        $today    = now()->toDateString();
        $cacheKey = "morning_digest_sent_{$today}";

        if (Cache::has($cacheKey)) {
            $this->info("Digest already sent today ({$today}).");
            return self::SUCCESS;
        }

        // Today's fixtures
        $todayFixtures = $football->getMatchesForDate($today);
        $upcoming      = collect($todayFixtures)
            ->filter(fn($m) => ($m['fixture']['status']['short'] ?? '') === 'NS')
            ->sortBy('fixture.date')
            ->values();

        if ($upcoming->isEmpty()) {
            $this->info("No matches today — skipping digest.");
            return self::SUCCESS;
        }

        // Yesterday's results
        $yesterday        = now()->subDay()->toDateString();
        $yesterdayMatches = $football->getMatchesForDate($yesterday);
        $results          = collect($yesterdayMatches)
            ->filter(fn($m) => in_array($m['fixture']['status']['short'] ?? '', ['FT', 'AET', 'PEN'], true))
            ->sortBy('fixture.date')
            ->values();

        // Top scorers (top 5)
        $scorers = $football->getTopScorers();

        // Build the digest message
        $lines = [];
        $lines[] = "☀️ *Good morning! World Cup 2026 Match Day*";
        $lines[] = "";

        // Yesterday's results
        if ($results->isNotEmpty()) {
            $lines[] = "📋 *Yesterday's Results:*";
            foreach ($results as $m) {
                $home   = $m['teams']['home']['name'];
                $away   = $m['teams']['away']['name'];
                $hg     = $m['goals']['home'] ?? 0;
                $ag     = $m['goals']['away'] ?? 0;
                $lines[] = "  {$home} *{$hg}–{$ag}* {$away}";
            }
            $lines[] = "";
        }

        // Today's fixtures
        $lines[] = "⚽ *Today's Matches:*";
        foreach ($upcoming as $m) {
            $home    = $m['teams']['home']['name'];
            $away    = $m['teams']['away']['name'];
            $round   = $m['league']['round'] ?? '';
            $kickoff = Carbon::parse($m['fixture']['date'])->timezone('Africa/Nairobi')->format('H:i');
            $lines[] = "  🕐 {$kickoff} — *{$home}* vs *{$away}* ({$round})";
        }
        $lines[] = "";

        // Top scorers
        if (!empty($scorers)) {
            $lines[] = "🥇 *Golden Boot:*";
            foreach (array_slice($scorers, 0, 5) as $s) {
                $name  = $s['player']['name'] ?? '?';
                $team  = $s['statistics'][0]['team']['name'] ?? '?';
                $goals = $s['statistics'][0]['goals']['total'] ?? 0;
                $lines[] = "  {$goals}⚽ {$name} ({$team})";
            }
            $lines[] = "";
        }

        $lines[] = "_Reply with any question about the tournament — GoalBot knows everything_ 🤖";
        $lines[] = "_https://goalbot.chat_";

        $message = implode("\n", $lines);

        // AI flavour — add a one-liner preview for the biggest match of the day
        $bigMatch = $upcoming->first();
        if ($bigMatch) {
            $aiPreview = $this->generateDayPreview(
                $bigMatch['teams']['home']['name'],
                $bigMatch['teams']['away']['name'],
                $bigMatch['league']['round'] ?? ''
            );
            if ($aiPreview) {
                $message .= "\n\n" . $aiPreview;
            }
        }

        // Send to all active subscribers
        $subscribers = Subscriber::where('is_active', true)
            ->where('notifications_enabled', true)
            ->get();

        if ($subscribers->isEmpty()) {
            $this->warn("No active subscribers.");
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($subscribers as $sub) {
            try {
                $whatsapp->sendAlert($sub->phone_number, $message);
                $sent++;
                usleep(200000);
            } catch (\Exception $e) {
                Log::error('Morning digest failed', ['sub' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        Cache::put($cacheKey, true, now()->addHours(20));
        $this->info("Morning digest sent to {$sent} subscribers.");

        return self::SUCCESS;
    }

    private function generateDayPreview(string $home, string $away, string $round): ?string
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) return null;

        $cacheKey = 'day_preview_' . md5("{$home}_{$away}");
        return Cache::remember($cacheKey, 86400, function () use ($home, $away, $round, $apiKey) {
            try {
                $response = Http::timeout(15)->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model'      => config('services.anthropic.model', 'claude-haiku-4-5'),
                    'max_tokens' => 120,
                    'system'     => 'You are GoalBot with the soul of Peter Drury. Write ONE dramatic sentence (max 120 chars) previewing the day\'s standout match. No emojis. Output only the sentence.',
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => "Today's standout match: {$home} vs {$away} ({$round}). One dramatic preview sentence.",
                    ]],
                    'temperature' => 0.9,
                ]);
                $text = trim($response->json('content.0.text', ''));
                return $text ? "🎙️ _{$text}_" : null;
            } catch (\Exception $e) {
                return null;
            }
        });
    }
}
