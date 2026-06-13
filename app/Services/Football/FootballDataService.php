<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    
    /**
     * Get all World Cup fixtures (entire tournament) - cached 1 hour
     */
    public function getAllWorldCupFixtures(): array
    {
        return Cache::remember('wc_all_fixtures', 3600, function () {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures", [
                    'league' => $this->getWorldCupLeagueId(),
                    'season' => 2026,
                ]);

                return $response->successful() ? $response->json('response', []) : [];
            } catch (\Exception $e) {
                Log::error('All WC fixtures error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Build a compact RAG context string for AI Q&A
     */
    public function buildFixturesContext(): string
    {
        $fixtures = $this->getAllWorldCupFixtures();
        if (empty($fixtures)) {
            return "No fixture data available right now.";
        }

        $now = now();
        $lines = ["FIFA World Cup 2026 (USA, Canada, Mexico) — June 11 to July 19, 2026"];
        $lines[] = "Current time: " . $now->toDateTimeString() . " UTC";
        $lines[] = "";
        $lines[] = "FIXTURES:";

        foreach ($fixtures as $f) {
            $home = $f['teams']['home']['name'] ?? '?';
            $away = $f['teams']['away']['name'] ?? '?';
            $date = $f['fixture']['date'] ?? '';
            $venue = $f['fixture']['venue']['name'] ?? '';
            $city = $f['fixture']['venue']['city'] ?? '';
            $status = $f['fixture']['status']['short'] ?? 'NS';
            $round = $f['league']['round'] ?? '';
            $homeGoals = $f['goals']['home'];
            $awayGoals = $f['goals']['away'];

            $score = ($homeGoals !== null && $awayGoals !== null) ? " [{$homeGoals}-{$awayGoals}]" : '';
            $lines[] = "- {$date} | {$round} | {$home} vs {$away}{$score} | {$venue}, {$city} | Status: {$status}";
        }

        // Append full live context for each live/today match
        $liveMatches  = $this->getLiveMatches();
        $todayMatches = $this->getMatchesForDate(now()->toDateString());
        $merged = collect($todayMatches)->keyBy(fn($m) => $m['fixture']['id']);
        foreach ($liveMatches as $lm) {
            $merged->put($lm['fixture']['id'], $lm);
        }

        $seen = [];
        foreach ($merged->values() as $f) {
            $fId   = $f['fixture']['id'];
            if (in_array($fId, $seen, true)) continue;
            $seen[] = $fId;

            $home   = $f['teams']['home']['name'] ?? '?';
            $away   = $f['teams']['away']['name'] ?? '?';
            $status = $f['fixture']['status']['short'] ?? 'NS';
            $minute = $f['fixture']['status']['elapsed'] ?? null;
            $hGoals = $f['goals']['home'] ?? 0;
            $aGoals = $f['goals']['away'] ?? 0;

            $lines[] = "";
            $lines[] = "=== LIVE MATCH: {$home} vs {$away} | {$hGoals}-{$aGoals} | {$status}" . ($minute ? " {$minute}'" : '') . " ===";

            // --- Lineups ---
            $lineups = $this->getLineups($fId);
            if (!empty($lineups)) {
                $lines[] = "LINEUPS:";
                foreach ($lineups as $team) {
                    $tName     = $team['team']['name'];
                    $formation = $team['formation'] ?? '?';
                    $starters  = collect($team['startXI'] ?? [])
                        ->map(fn($p) => $p['player']['name'] . ' (#' . $p['player']['number'] . ')')
                        ->implode(', ');
                    $bench = collect($team['substitutes'] ?? [])
                        ->map(fn($p) => $p['player']['name'])
                        ->implode(', ');
                    $lines[] = "  {$tName} [{$formation}]: {$starters}";
                    if ($bench) $lines[] = "  {$tName} Bench: {$bench}";
                }
            }

            // --- Events (goals, cards, subs) ---
            $events = $this->getMatchEvents($fId);
            if (!empty($events)) {
                $lines[] = "MATCH EVENTS:";
                foreach ($events as $e) {
                    $min    = $e['time']['elapsed'] ?? '?';
                    $type   = $e['type'] ?? '?';
                    $detail = $e['detail'] ?? '';
                    $team   = $e['team']['name'] ?? '?';
                    $player = $e['player']['name'] ?? '?';
                    $assist = $e['assist']['name'] ?? null;

                    if ($type === 'Goal') {
                        $lines[] = "  {$min}' GOAL — {$team} | {$player}" . ($assist ? " (assist: {$assist})" : '');
                    } elseif ($type === 'Card') {
                        $lines[] = "  {$min}' {$detail} — {$team} | {$player}";
                    } elseif ($type === 'subst') {
                        $lines[] = "  {$min}' SUB — {$team} | OFF: {$player}" . ($assist ? " ON: {$assist}" : '');
                    } else {
                        $lines[] = "  {$min}' {$type} {$detail} — {$team} | {$player}";
                    }
                }
            }

            // --- Live stats ---
            $stats = $this->getLiveStats($fId);
            if (!empty($stats)) {
                $lines[] = "LIVE STATS:";
                $statMap = [];
                foreach ($stats as $teamStats) {
                    $tName = $teamStats['team']['name'];
                    foreach ($teamStats['statistics'] ?? [] as $s) {
                        $statMap[$s['type']][$tName] = $s['value'] ?? '-';
                    }
                }
                $show = ['Ball Possession', 'Total Shots', 'Shots on Goal', 'Corner Kicks', 'Fouls', 'Yellow Cards', 'Red Cards', 'Offsides'];
                foreach ($show as $stat) {
                    if (isset($statMap[$stat])) {
                        $hv = $statMap[$stat][$home] ?? '-';
                        $av = $statMap[$stat][$away] ?? '-';
                        $lines[] = "  {$stat}: {$home} {$hv} | {$away} {$av}";
                    }
                }
            }

            // --- Predictions ---
            $predictions = $this->getPredictions($fId);
            if ($predictions) {
                $pct     = $predictions['predictions']['percent'] ?? [];
                $advice  = $predictions['predictions']['advice'] ?? null;
                $winner  = $predictions['predictions']['winner']['name'] ?? null;
                $goalsH  = $predictions['predictions']['goals']['home'] ?? null;
                $goalsA  = $predictions['predictions']['goals']['away'] ?? null;
                $lines[] = "PREDICTIONS:";
                if ($winner)  $lines[] = "  Predicted winner: {$winner}";
                if ($advice)  $lines[] = "  Advice: {$advice}";
                if ($pct)     $lines[] = "  Win%: {$home} " . ($pct['home'] ?? '?') . " | Draw " . ($pct['draw'] ?? '?') . " | {$away} " . ($pct['away'] ?? '?');
                if ($goalsH !== null) $lines[] = "  Predicted goals: {$home} {$goalsH} | {$away} {$goalsA}";
            }

            // --- Injuries ---
            $injuries = $this->getInjuries($fId);
            if (!empty($injuries)) {
                $lines[] = "INJURIES:";
                foreach ($injuries as $inj) {
                    $pName  = $inj['player']['name'] ?? '?';
                    $type   = $inj['player']['type'] ?? '?';
                    $reason = $inj['player']['reason'] ?? '?';
                    $tName  = $inj['team']['name'] ?? '?';
                    $lines[] = "  {$tName} | {$pName} — {$type} ({$reason})";
                }
            }

            // --- Odds ---
            $odds = $this->getOdds($fId);
            if (!empty($odds)) {
                $lines[] = "ODDS (" . ($odds['bookmaker'] ?? 'bookmaker') . "):";
                foreach ($odds['bets'] ?? [] as $market => $values) {
                    $parts = [];
                    foreach ($values as $label => $odd) {
                        $parts[] = "{$label}: {$odd}";
                    }
                    $lines[] = "  {$market}: " . implode(' | ', $parts);
                }
            }

            // --- LiveScore commentary ---
            $lsSlug = $this->getLiveScoreFullSlug($home, $away, $fId);
            if ($lsSlug) {
                $commentarySvc = app(LiveScoreCommentaryService::class);
                $commentaryContext = $commentarySvc->buildCommentaryContext($lsSlug, 25);
                if ($commentaryContext) {
                    $lines[] = "";
                    $lines[] = $commentaryContext;
                }
            }
        }

        // --- Group Standings ---
        $standings = $this->getStandings();
        if (!empty($standings)) {
            $lines[] = "";
            $lines[] = "GROUP STANDINGS:";
            foreach ($standings as $group) {
                $groupName = $group[0]['group'] ?? 'Group';
                $lines[] = "  {$groupName}:";
                foreach ($group as $entry) {
                    $team  = $entry['team']['name'];
                    $pts   = $entry['points'];
                    $w     = $entry['all']['win'];
                    $d     = $entry['all']['draw'];
                    $l     = $entry['all']['lose'];
                    $gd    = $entry['goalsDiff'];
                    $rank  = $entry['rank'];
                    $lines[] = "    {$rank}. {$team} — {$pts}pts ({$w}W {$d}D {$l}L, GD:{$gd})";
                }
            }
        }

        // --- Top Scorers ---
        $scorers = $this->getTopScorers();
        if (!empty($scorers)) {
            $lines[] = "";
            $lines[] = "TOP SCORERS (Golden Boot):";
            foreach (array_slice($scorers, 0, 10) as $s) {
                $name  = $s['player']['name'] ?? '?';
                $team  = $s['statistics'][0]['team']['name'] ?? '?';
                $goals = $s['statistics'][0]['goals']['total'] ?? 0;
                $lines[] = "  {$goals} goals — {$name} ({$team})";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get today's completed and live match results
     */
    public function getTodayResults(): array
    {
        $today = now()->toDateString();
        $all = $this->getMatchesForDate($today);
        return collect($all)->filter(fn($m) =>
            in_array($m['fixture']['status']['short'] ?? '', ['FT', 'AET', 'PEN', '1H', '2H', 'HT', 'ET', 'LIVE'], true)
        )->values()->toArray();
    }

    /**
     * Get upcoming matches (not yet started)
     */
    public function getUpcomingMatches(int $days = 1): array
    {
        $fixtures = $this->getAllWorldCupFixtures();
        $now = now();
        $cutoff = now()->addDays($days);
        return collect($fixtures)->filter(function ($m) use ($now, $cutoff) {
            if (($m['fixture']['status']['short'] ?? '') !== 'NS') return false;
            $date = \Carbon\Carbon::parse($m['fixture']['date']);
            return $date->gte($now) && $date->lte($cutoff);
        })->sortBy('fixture.date')->values()->toArray();
    }

    /**
     * Find the next match for a specific team name (case-insensitive partial)
     */
    public function getTeamNextMatch(string $teamName): ?array
    {
        $fixtures = $this->getAllWorldCupFixtures();
        $search = strtolower($teamName);
        $now = now();
        return collect($fixtures)->filter(function ($m) use ($search, $now) {
            if (($m['fixture']['status']['short'] ?? '') !== 'NS') return false;
            $home = strtolower($m['teams']['home']['name'] ?? '');
            $away = strtolower($m['teams']['away']['name'] ?? '');
            return str_contains($home, $search) || str_contains($away, $search);
        })->sortBy('fixture.date')->first();
    }

    /**
     * Get lineups for a fixture
     */
    public function getLineups(int $fixtureId): array
    {
        return Cache::remember("lineups_{$fixtureId}", 300, function () use ($fixtureId) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures/lineups", [
                    'fixture' => $fixtureId,
                ]);
                return $response->successful() ? $response->json('response', []) : [];
            } catch (\Exception $e) {
                Log::error('Lineups error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get live statistics for a fixture (possession, shots, corners etc.)
     */
    public function getLiveStats(int $fixtureId): array
    {
        return Cache::remember("live_stats_{$fixtureId}", 30, function () use ($fixtureId) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures/statistics", [
                    'fixture' => $fixtureId,
                ]);
                return $response->successful() ? $response->json('response', []) : [];
            } catch (\Exception $e) {
                Log::error('Live stats error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get predictions for a fixture
     */
    public function getPredictions(int $fixtureId): ?array
    {
        return Cache::remember("predictions_{$fixtureId}", 3600, function () use ($fixtureId) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key'  => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/predictions", ['fixture' => $fixtureId]);
                return $response->successful() ? ($response->json('response.0') ?? null) : null;
            } catch (\Exception $e) {
                Log::error('Predictions error', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get injuries for a fixture
     */
    public function getInjuries(int $fixtureId): array
    {
        return Cache::remember("injuries_{$fixtureId}", 3600, function () use ($fixtureId) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key'  => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/injuries", ['fixture' => $fixtureId]);
                return $response->successful() ? ($response->json('response') ?? []) : [];
            } catch (\Exception $e) {
                Log::error('Injuries error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get odds for a fixture (first bookmaker, key markets)
     */
    public function getOdds(int $fixtureId): array
    {
        return Cache::remember("odds_{$fixtureId}", 1800, function () use ($fixtureId) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key'  => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/odds", ['fixture' => $fixtureId]);
                $bookmaker = $response->json('response.0.bookmakers.0') ?? null;
                if (!$bookmaker) return [];
                $result = ['bookmaker' => $bookmaker['name']];
                foreach ($bookmaker['bets'] ?? [] as $bet) {
                    $result['bets'][$bet['name']] = collect($bet['values'])
                        ->pluck('odd', 'value')
                        ->toArray();
                }
                return $result;
            } catch (\Exception $e) {
                Log::error('Odds error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get head-to-head record between two teams (last 10 meetings)
     */
    public function getHeadToHead(int $team1Id, int $team2Id): array
    {
        return Cache::remember("h2h_{$team1Id}_{$team2Id}", 86400, function () use ($team1Id, $team2Id) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key'  => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures/headtohead", [
                    'h2h'  => "{$team1Id}-{$team2Id}",
                    'last' => 10,
                ]);
                return $response->successful() ? ($response->json('response') ?? []) : [];
            } catch (\Exception $e) {
                Log::error('H2H error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get last N results for a team
     */
    public function getTeamForm(int $teamId, int $last = 5): array
    {
        return Cache::remember("form_{$teamId}_{$last}", 3600, function () use ($teamId, $last) {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key'  => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/fixtures", [
                    'team'   => $teamId,
                    'last'   => $last,
                    'status' => 'FT-AET-PEN',
                ]);
                return $response->successful() ? ($response->json('response') ?? []) : [];
            } catch (\Exception $e) {
                Log::error('Team form error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get group standings for the World Cup
     */
    public function getStandings(): array
    {
        return Cache::remember('wc_standings', 1800, function () {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key'  => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/standings", [
                    'league' => $this->getWorldCupLeagueId(),
                    'season' => 2026,
                ]);
                return $response->successful() ? ($response->json('response.0.league.standings') ?? []) : [];
            } catch (\Exception $e) {
                Log::error('Standings error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get top scorers for the World Cup
     */
    public function getTopScorers(): array
    {
        return Cache::remember('wc_top_scorers', 3600, function () {
            try {
                $response = Http::withHeaders([
                    'x-rapidapi-key'  => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                ])->get("{$this->baseUrl}/players/topscorers", [
                    'league' => $this->getWorldCupLeagueId(),
                    'season' => 2026,
                ]);
                return $response->successful() ? ($response->json('response') ?? []) : [];
            } catch (\Exception $e) {
                Log::error('Top scorers error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get today's live fixture ID (first live/in-progress WC match)
     */
    public function getTodayFixtureId(): ?int
    {
        $live = $this->getLiveMatches();
        if (!empty($live)) {
            return $live[0]['fixture']['id'] ?? null;
        }
        // Fall back to today's first match
        $today = $this->getMatchesForDate(now()->toDateString());
        return !empty($today) ? ($today[0]['fixture']['id'] ?? null) : null;
    }

    /**
     * Get full LiveScore slug: "team-a-vs-team-b/LIVESCORE_ID"
     * LiveScore ID must be registered via registerLiveScoreId().
     */
    public function getLiveScoreFullSlug(string $home, string $away, int $fixtureId): ?string
    {
        // Auto-discover slug from LiveScore fixtures page
        $commentary = app(LiveScoreCommentaryService::class);
        return $commentary->discoverSlug($home, $away, $fixtureId);
    }

    /**
     * Register a LiveScore match ID against an API-Football fixture ID.
     * Call this once per match (e.g. from admin or a mapping config).
     */
    public function registerLiveScoreId(int $fixtureId, string $liveScoreId): void
    {
        Cache::put("livescore_id_{$fixtureId}", $liveScoreId, 86400);
    }

    private function getWorldCupLeagueId(): int
    {
        // World Cup league ID in API-Football
        return 1;
    }
}
