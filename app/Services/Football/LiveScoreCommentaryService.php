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
            // First check database — only trust records where the slug ID differs from fixture_id
            $record = \App\Models\LiveScoreCommentaryUrl::where('fixture_id', $matchId)->first();
            if ($record && $record->livescore_slug) {
                preg_match('/\/(\d+)$/', $record->livescore_slug, $m);
                $slugId = $m[1] ?? null;
                // Reject if the slug ID matches the API-Football fixture_id (wrong)
                if ($slugId && $slugId !== (string)$matchId) {
                    Log::info('LiveScore slug found in database', ['fixture_id' => $matchId, 'slug' => $record->livescore_slug]);
                    return $record->livescore_slug;
                }
                Log::warning('DB slug rejected - uses API-Football ID', ['fixture_id' => $matchId, 'slug' => $record->livescore_slug]);
            }

            // Search both results and fixtures pages on LiveScore
            $pages = [
                "https://www.livescore.com/en/football/international/world-cup-2026/results/",
                "https://www.livescore.com/en/football/international/world-cup-2026/fixtures/",
            ];

            foreach ($pages as $url) {
                try {
                    $response = Http::withHeaders($this->headers)->timeout(10)->get($url);
                    if (!$response->successful()) continue;

                    preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $response->body(), $m);
                    if (empty($m[1])) continue;

                    $json = json_decode($m[1], true);
                    if (!$json) continue;

                    // Search all sections for events
                    $sections = $json['props']['pageProps']['initialData']['sections'] ?? [];
                    foreach ($sections as $section) {
                        $events = $section['events'] ?? [];
                        foreach ($events as $event) {
                            $eventHome = strtolower($event['homeTeamName'] ?? '');
                            $eventAway = strtolower($event['awayTeamName'] ?? '');
                            $searchHome = strtolower($homeTeam);
                            $searchAway = strtolower($awayTeam);

                            // Exact match or fuzzy (contains check for special chars like Türkiye/Turkey)
                            $homeMatch = $eventHome === $searchHome || str_contains($eventHome, $searchHome) || str_contains($searchHome, $eventHome);
                            $awayMatch = $eventAway === $searchAway || str_contains($eventAway, $searchAway) || str_contains($searchAway, $eventAway);

                            if ($homeMatch && $awayMatch) {
                                $eventId = $event['id'] ?? '';
                                if ($eventId) {
                                    $slugHome = strtolower(str_replace(' ', '-', $event['homeTeamName'] ?? ''));
                                    $slugAway = strtolower(str_replace(' ', '-', $event['awayTeamName'] ?? ''));
                                    $slug = "{$slugHome}-vs-{$slugAway}/{$eventId}";

                                    // Save correct slug to DB
                                    \App\Models\LiveScoreCommentaryUrl::updateOrCreate(
                                        ['fixture_id' => $matchId],
                                        ['home_team' => $homeTeam, 'away_team' => $awayTeam, 'livescore_slug' => $slug]
                                    );

                                    Log::info('LiveScore slug discovered', ['fixture_id' => $matchId, 'slug' => $slug]);
                                    return $slug;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('LiveScore slug discovery error', ['url' => $url, 'error' => $e->getMessage()]);
                }
            }

            Log::warning('LiveScore slug not found on any page', ['home' => $homeTeam, 'away' => $awayTeam, 'matchId' => $matchId]);
            return null;
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
