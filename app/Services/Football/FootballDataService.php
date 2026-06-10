<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FootballDataService
{
    private string $apiKey;
    private string $baseUrl;
    
    public function __construct()
    {
        $this->apiKey = config('services.football.key');
        $this->baseUrl = config('services.football.url', 'https://v3.football.api-sports.io');
    }
    
    /**
     * Get matches for a specific date
     */
    public function getMatchesForDate(string $date): array
    {
        $cacheKey = "matches_{$date}";
        
        return Cache::remember($cacheKey, 30, function () use ($date) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures", [
                    'date' => $date,
                    'league' => $this->getWorldCupLeagueId(),
                    'season' => 2026,
                ]);
                
                if ($response->successful()) {
                    return $response->json('response', []);
                }
                
                Log::error('Football API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return [];
            } catch (\Exception $e) {
                Log::error('Football API exception', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }
    
    /**
     * Get live matches (currently in progress)
     */
    public function getLiveMatches(): array
    {
        $cacheKey = 'matches_live';
        
        return Cache::remember($cacheKey, 30, function () {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures", [
                    'live' => 'all',
                    'league' => $this->getWorldCupLeagueId(),
                    'season' => 2026,
                ]);
                
                return $response->successful() ? $response->json('response', []) : [];
            } catch (\Exception $e) {
                Log::error('Live matches error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }
    
    /**
     * Get matches starting between two times (for reminders)
     */
    public function getMatchesStartingBetween(string $start, string $end): array
    {
        $allMatches = $this->getMatchesForDate(substr($start, 0, 10));
        
        return collect($allMatches)->filter(function ($match) use ($start, $end) {
            $kickoff = $match['fixture']['date'];
            return $kickoff >= $start && $kickoff <= $end;
        })->values()->toArray();
    }
    
    /**
     * Get match statistics
     */
    public function getMatchStats(int $fixtureId): array
    {
        $cacheKey = "match_stats_{$fixtureId}";
        
        return Cache::remember($cacheKey, 60, function () use ($fixtureId) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures/statistics", [
                    'fixture' => $fixtureId,
                ]);
                
                return $response->successful() ? $response->json('response', []) : [];
            } catch (\Exception $e) {
                Log::error('Match stats error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }
    
    /**
     * Get match events (goals, cards, subs)
     */
    public function getMatchEvents(int $fixtureId): array
    {
        $cacheKey = "match_events_{$fixtureId}";
        
        return Cache::remember($cacheKey, 30, function () use ($fixtureId) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures/events", [
                    'fixture' => $fixtureId,
                ]);
                
                return $response->successful() ? $response->json('response', []) : [];
            } catch (\Exception $e) {
                Log::error('Match events error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }
    
    private function getWorldCupLeagueId(): int
    {
        // World Cup league ID in API-Football
        return 1;
    }
}
