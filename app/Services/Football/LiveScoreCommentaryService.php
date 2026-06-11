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
     * Notable commentary NOT covered by the event detector — big chances, post hits, offsides, dangerous moments
     */
    private array $notableKeywords = [
        'post!',
        'crossbar',
        'offside',
        'great save',
        'saves',
        'chance',
        'dangerous',
        'shoots',
        'almost',
        'nearly',
        'header',
        'free kick',
        'corner',
        'own goal',   // sometimes commentary describes before API updates
        'video review',
    ];

    /**
     * Get notable commentary entries NOT already covered by the event detector.
     */
    public function getHighlights(string $matchSlug, int $limit = 10): array
    {
        $entries = $this->getCommentary($matchSlug);

        return collect($entries)
            ->filter(function ($c) {
                $text = strtolower($c['text']);

                // Skip anything the event detector already handles
                foreach ($this->coveredByDetector as $kw) {
                    if (str_contains($text, $kw)) return false;
                }

                // Only keep genuinely notable moments
                foreach ($this->notableKeywords as $kw) {
                    if (str_contains($text, $kw)) return true;
                }

                return false;
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
}
