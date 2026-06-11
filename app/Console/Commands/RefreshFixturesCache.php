<?php

namespace App\Console\Commands;

use App\Services\Football\FootballDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshFixturesCache extends Command
{
    protected $signature = 'fixtures:refresh';
    protected $description = 'Refresh the cached World Cup fixtures used for AI Q&A (RAG)';

    public function handle(FootballDataService $football): int
    {
        $apiKey = config('services.football.key');
        $baseUrl = config('services.football.url', 'https://v3.football.api-sports.io');

        if (empty($apiKey)) {
            $this->error('FOOTBALL_API_KEY not configured');
            return self::FAILURE;
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
            // Force-overwrite the cache used by buildFixturesContext
            Cache::put('wc_all_fixtures', $fixtures, 3600);

            $this->info('Refreshed ' . count($fixtures) . ' World Cup fixtures');
            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('fixtures:refresh failed', ['error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
