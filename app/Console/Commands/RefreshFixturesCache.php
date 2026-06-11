<?php

namespace App\Console\Commands;

use App\Services\Football\FootballDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshFixturesCache extends Command
{
    protected $signature = 'fixtures:refresh {--force : Force refresh regardless of conditions}';
    protected $description = 'Refresh the cached World Cup fixtures used for AI Q&A (RAG)';

    // Live status codes from API-Football
    private const LIVE_STATUSES = ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'];

    public function handle(FootballDataService $football): int
    {
        $apiKey = config('services.football.key');
        $baseUrl = config('services.football.url', 'https://v3.football.api-sports.io');

        if (empty($apiKey)) {
            $this->error('FOOTBALL_API_KEY not configured');
            return self::FAILURE;
        }

        // Smart refresh logic: skip if conditions not met (saves API quota)
        if (!$this->option('force') && !$this->shouldRefresh()) {
            $this->info('Skipped: no live matches and cache is fresh');
            return self::SUCCESS;
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'x-rapidapi-key' => $apiKey,
                'x-rapidapi-host' => 'v3.football.api-sports.io',
            ])->get("{$baseUrl}/fixtures", [
                'league' => 1,
                'season' => 2026,
            ]);

            if (!$response->successful()) {
                $this->error('API call failed: ' . $response->status());
                return self::FAILURE;
            }

            $fixtures = $response->json('response', []);
            // Long TTL (24h) - we control freshness via this command, not TTL
            Cache::put('wc_all_fixtures', $fixtures, 86400);
            Cache::put('wc_fixtures_refreshed_at', now()->timestamp, 86400);

            $live = $this->countLive($fixtures);
            $this->info("Refreshed " . count($fixtures) . " fixtures ({$live} live)");
            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('fixtures:refresh failed', ['error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Decide whether to hit the API.
     * - Always refresh if cache is empty
     * - Refresh every minute when there's a live match
     * - Refresh every 30 minutes otherwise
     */
    private function shouldRefresh(): bool
    {
        $fixtures = Cache::get('wc_all_fixtures');
        if (empty($fixtures)) {
            return true;
        }

        $lastRefresh = Cache::get('wc_fixtures_refreshed_at', 0);
        $secondsSince = now()->timestamp - $lastRefresh;

        // If a match is live, refresh every minute
        if ($this->countLive($fixtures) > 0) {
            return $secondsSince >= 60;
        }

        // Otherwise, refresh every 30 minutes
        return $secondsSince >= 1800;
    }

    private function countLive(array $fixtures): int
    {
        $live = 0;
        foreach ($fixtures as $f) {
            if (in_array($f['fixture']['status']['short'] ?? '', self::LIVE_STATUSES, true)) {
                $live++;
            }
        }
        return $live;
    }
}
