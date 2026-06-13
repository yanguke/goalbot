<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiveScoreCommentaryService
{
    private array $headers = [
        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Accept'          => 'text/html,application/xhtml+xml,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ];

    /**
     * Fetch minute-by-minute commentary for a LiveScore match URL slug.
     * e.g. "mexico-vs-south-africa/1417909"
     */
    public function getCommentary(string $matchSlug): array
    {
        return Cache::remember("livescore_commentary_{$matchSlug}", 30, function () use ($matchSlug) {
            try {
                $url = "https://www.livescore.com/en/football/international/world-cup-2026/{$matchSlug}/?tab=commentary";
                $response = Http::withHeaders($this->headers)->timeout(10)->get($url);

                if (!$response->successful()) {
                    Log::warning('LiveScore commentary fetch failed', ['status' => $response->status()]);
                    return [];
                }

                return $this->parseCommentary($response->body());
            } catch (\Exception $e) {
                Log::error('LiveScore commentary error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Build commentary context string for RAG (last N entries)
     */
    public function buildCommentaryContext(string $matchSlug, int $limit = 20): string
    {
        $entries = $this->getCommentary($matchSlug);
        if (empty($entries)) return '';

        $lines = ["LIVE COMMENTARY (latest {$limit} entries):"];
        foreach (array_slice($entries, 0, $limit) as $c) {
            $lines[] = "  " . $c['time'] . " — " . $c['text'];
        }
        return implode("\n", $lines);
    }

    /**
     * Events already handled by the API-Football event detector.
     * Commentary entries matching these are suppressed to avoid duplicate alerts.
     */
    private array $coveredByDetector = [
        'goal',         // detectGoal
        'scores',       // goal variants
        'yellow card',  // detectCards
        'red card',     // detectCards
        'second yellow',
        'substitut',    // detectSubstitutions
        'half-time',    // isHalfTime
        'half time',
        'full time',    // isFullTime
        'full-time',
        'kick off',     // kickoff in PollMatches
        'kicks off',
        'penalty scored',   // detectPenaltyEvents / goal type
        'penalty missed',
        'var',          // detectVarEvents
    ];

    /**
     * Noisy low-value entries to suppress (routine ball movement).
     */
    private array $suppressKeywords = [
        'take a throw-in',
        'goal kick for',
        'are in control of the ball',
        'are trying to create something',
        'ball possession:',
        'relieves the pressure with a clearance',
        'intercepts a cross',
        'makes the tackle and wins possession',
        'successfully finds a teammate',
    ];

    /**
     * Get all commentary NOT covered by the event detector and NOT routine noise.
     * This sends every meaningful moment — fouls, chances, shots, corners, subs etc.
     */
    public function getHighlights(string $matchSlug, int $limit = 50): array
    {
        $entries = $this->getCommentary($matchSlug);

        return collect($entries)
            ->filter(function ($c) {
                $text = strtolower($c['text']);

                // Skip events the API-Football detector already broadcasts
                foreach ($this->coveredByDetector as $kw) {
                    if (str_contains($text, $kw)) return false;
                }

                // Skip routine low-value noise
                foreach ($this->suppressKeywords as $kw) {
                    if (str_contains($text, $kw)) return false;
                }

                return true;
            })
            ->take($limit)
            ->values()
            ->toArray();
    }

    private function parseCommentary(string $html): array
    {
        preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches);
        if (empty($matches[1])) return [];

        $json = json_decode($matches[1], true);
        if (!$json) return [];

        return $this->findKey($json['props']['pageProps'] ?? [], 'commentary') ?? [];
    }

    private function findKey(array $data, string $key, int $depth = 0): ?array
    {
        if ($depth > 6) return null;
        foreach ($data as $k => $v) {
            if (strtolower((string)$k) === strtolower($key) && is_array($v) && !empty($v)) {
                return $v;
            }
            if (is_array($v)) {
                $result = $this->findKey($v, $key, $depth + 1);
                if ($result !== null) return $result;
            }
        }
        return null;
    }

    /**
     * Discover the LiveScore match slug by fetching the competition page and finding the match.
     * Returns slug like "qatar-vs-switzerland/1417916"
     */
    public function discoverSlug(string $homeTeam, string $awayTeam, int $matchId): ?string
    {
        $cacheKey = "livescore_slug_{$matchId}";
        return Cache::remember($cacheKey, 3600, function () use ($homeTeam, $awayTeam, $matchId) {
            // First check if we have it in the database
            $record = \App\Models\LiveScoreCommentaryUrl::where('fixture_id', $matchId)->first();
            if ($record && $record->livescore_slug) {
                Log::info('LiveScore slug found in database', ['fixture_id' => $matchId, 'slug' => $record->livescore_slug]);
                return $record->livescore_slug;
            }
            try {
                // Fetch the World Cup 2026 fixtures page
                $url = "https://www.livescore.com/en/football/international/world-cup-2026/fixtures/";
                $response = Http::withHeaders($this->headers)->timeout(10)->get($url);

                if (!$response->successful()) {
                    Log::warning('LiveScore fixtures page fetch failed', ['status' => $response->status()]);
                    return null;
                }

                // Parse JSON from the page
                preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $response->body(), $matches);
                if (empty($matches[1])) return null;

                $json = json_decode($matches[1], true);
                if (!$json) return null;

                // Navigate to events array
                $events = $json['props']['pageProps']['initialData']['sections'][0]['events'] ?? [];
                if (empty($events)) return null;

                // Find matching event by team names (case-insensitive)
                foreach ($events as $event) {
                    $eventHome = strtolower($event['homeTeamName'] ?? '');
                    $eventAway = strtolower($event['awayTeamName'] ?? '');
                    $searchHome = strtolower($homeTeam);
                    $searchAway = strtolower($awayTeam);

                    if (
                        ($eventHome === $searchHome && $eventAway === $searchAway) ||
                        ($eventHome === $searchAway && $eventAway === $searchHome)
                    ) {
                        $eventId = $event['id'] ?? '';
                        if ($eventId) {
                            // Build slug from team names and ID
                            $slugHome = strtolower(str_replace(' ', '-', $event['homeTeamName'] ?? ''));
                            $slugAway = strtolower(str_replace(' ', '-', $event['awayTeamName'] ?? ''));
                            return "{$slugHome}-vs-{$slugAway}/{$eventId}";
                        }
                    }
                }

                // Fall back: construct slug from team names and try to verify it exists
                Log::info('LiveScore slug not found on fixtures page, trying constructed slug', [
                    'home' => $homeTeam,
                    'away' => $awayTeam,
                    'matchId' => $matchId
                ]);
                
                $fallbackSlug = strtolower(str_replace(' ', '-', $homeTeam)) . '-vs-' . strtolower(str_replace(' ', '-', $awayTeam)) . "/{$matchId}";
                
                // Verify the constructed slug actually exists by checking the page
                if ($this->verifySlugExists($fallbackSlug)) {
                    Log::info('Constructed slug verified successfully', ['slug' => $fallbackSlug]);
                    return $fallbackSlug;
                }
                
                Log::warning('Both discovery and fallback failed for match', [
                    'home' => $homeTeam,
                    'away' => $awayTeam,
                    'matchId' => $matchId,
                    'fallback' => $fallbackSlug
                ]);
                return null;
            } catch (\Exception $e) {
                Log::error('LiveScore slug discovery error', [
                    'error' => $e->getMessage(),
                    'home' => $homeTeam,
                    'away' => $awayTeam,
                    'matchId' => $matchId
                ]);
                return null;
            }
        });
    }

    /**
     * Verify that a constructed slug actually exists by checking the page loads successfully
     */
    private function verifySlugExists(string $slug): bool
    {
        try {
            $url = "https://www.livescore.com/en/football/international/world-cup-2026/{$slug}/";
            $response = Http::withHeaders($this->headers)->timeout(5)->get($url);
            return $response->successful() && str_contains($response->body(), 'commentary');
        } catch (\Exception $e) {
            return false;
        }
    }
}
