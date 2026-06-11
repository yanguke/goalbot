<?php

namespace App\Console\Commands;

use App\Services\Football\FootballDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RegisterLiveScore extends Command
{
    protected $signature = 'livescore:register
                            {url : Full LiveScore URL e.g. https://www.livescore.com/en/football/international/world-cup-2026/mexico-vs-south-africa/1417909/}
                            {fixture_id : API-Football fixture ID}';

    protected $description = 'Register a LiveScore match URL against an API-Football fixture ID';

    public function handle(FootballDataService $football): int
    {
        $url       = $this->argument('url');
        $fixtureId = (int) $this->argument('fixture_id');

        // Parse LiveScore match ID from URL — last numeric segment
        preg_match('/\/(\d+)\/?(\?.*)?$/', $url, $matches);
        $liveScoreId = $matches[1] ?? null;

        if (!$liveScoreId) {
            $this->error("Could not parse LiveScore match ID from URL: {$url}");
            return self::FAILURE;
        }

        // Persist to DB-backed cache (survives restarts)
        Cache::put("livescore_id_{$fixtureId}", $liveScoreId, now()->addDays(30));

        // Also seed the seeded flag as false so commentary starts fresh
        Cache::forget("commentary_seeded_{$fixtureId}");

        $this->info("Registered:");
        $this->line("  Fixture ID  : {$fixtureId}");
        $this->line("  LiveScore ID: {$liveScoreId}");
        $this->line("  URL         : {$url}");
        $this->line("  Commentary will be seeded on first poll (no old entries sent).");

        return self::SUCCESS;
    }
}
