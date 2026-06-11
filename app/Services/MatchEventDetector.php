<?php

namespace App\Services;

use App\Models\MatchState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MatchEventDetector
{
    /**
     * Detect events by comparing current match data with stored state
     */
    public function detectEvents(array $currentMatch): array
    {
        $matchId = $currentMatch['fixture']['id'];
        $events = [];
        
        // Get or create stored state
        $storedState = MatchState::where('external_match_id', $matchId)->first();
        $previous = $storedState?->state_snapshot ?? [];
        
        // If no previous state, just store current and return empty
        if (empty($previous)) {
            $this->storeState($matchId, $currentMatch);
            return [];
        }
        
        // Check for various events (kickoff handled separately in PollMatches with DB dedup)
        if ($goalData = $this->detectGoal($previous, $currentMatch)) {
            $events[] = ['type' => 'goal', 'data' => $goalData];
        }

        if ($this->isHalfTime($previous, $currentMatch)) {
            $events[] = ['type' => 'halftime', 'data' => $this->buildHalftimeData($currentMatch)];
        }

        if ($this->isSecondHalfKickoff($previous, $currentMatch)) {
            $events[] = ['type' => 'second_half', 'data' => $this->buildHalftimeData($currentMatch)];
        }

        if ($this->isFullTime($previous, $currentMatch)) {
            $events[] = ['type' => 'fulltime', 'data' => $this->buildFulltimeData($currentMatch)];
        }

        foreach ($this->detectCards($previous, $currentMatch) as $card) {
            $events[] = ['type' => $card['event_type'], 'data' => $card];
        }

        foreach ($this->detectVarEvents($previous, $currentMatch) as $var) {
            $events[] = ['type' => 'var', 'data' => $var];
        }

        foreach ($this->detectPenaltyEvents($previous, $currentMatch) as $pen) {
            $events[] = ['type' => $pen['event_type'], 'data' => $pen];
        }

        foreach ($this->detectSubstitutions($previous, $currentMatch) as $sub) {
            $events[] = ['type' => 'substitution', 'data' => $sub];
        }
        
        // Update stored state
        $this->storeState($matchId, $currentMatch);
        
        return $events;
    }
    
    private function isKickoff(array $previous, array $current): bool
    {
        $prevStatus = $previous['fixture']['status']['short'] ?? 'NS';
        $currStatus = $current['fixture']['status']['short'] ?? 'NS';
        return $prevStatus === 'NS' && ($currStatus === '1H' || $currStatus === 'LIVE');
    }

    private function isSecondHalfKickoff(array $previous, array $current): bool
    {
        $prevStatus = $previous['fixture']['status']['short'] ?? '';
        $currStatus = $current['fixture']['status']['short'] ?? '';
        return $prevStatus === 'HT' && $currStatus === '2H';
    }
    
    private function detectGoal(array $previous, array $current): ?array
    {
        $prevHome = $previous['goals']['home'] ?? 0;
        $prevAway = $previous['goals']['away'] ?? 0;
        $currHome = $current['goals']['home'] ?? 0;
        $currAway = $current['goals']['away'] ?? 0;

        $isHome = $currHome > $prevHome;
        $isAway = $currAway > $prevAway;
        if (!$isHome && !$isAway) return null;

        $scoringTeam  = $isHome ? $current['teams']['home']['name'] : $current['teams']['away']['name'];
        $minute       = $current['fixture']['status']['elapsed'] ?? '?';
        $goalEvent    = $this->extractGoalEvent($current, $isHome);
        $detail       = $goalEvent['detail'] ?? 'Normal Goal';
        $scorer       = $goalEvent['player']['name'] ?? null;
        $assist       = $goalEvent['assist']['name'] ?? null;

        return [
            'team'       => $scoringTeam,
            'score'      => "{$currHome}-{$currAway}",
            'minute'     => $minute,
            'scorer'     => $scorer,
            'assist'     => $assist,
            'goal_type'  => $detail, // Normal Goal | Own Goal | Penalty
            'is_own_goal'=> str_contains($detail, 'Own'),
            'is_penalty' => $detail === 'Penalty',
        ];
    }
    
    private function isHalfTime(array $previous, array $current): bool
    {
        $prevStatus = $previous['fixture']['status']['short'] ?? '';
        $currStatus = $current['fixture']['status']['short'] ?? '';
        
        return $prevStatus === '1H' && $currStatus === 'HT';
    }
    
    private function isFullTime(array $previous, array $current): bool
    {
        $prevStatus = $previous['fixture']['status']['short'] ?? '';
        $currStatus = $current['fixture']['status']['short'] ?? '';
        
        $wasPlaying = in_array($prevStatus, ['1H', '2H', 'ET', 'BT']);
        $isFinished = in_array($currStatus, ['FT', 'AET', 'PEN']);
        
        return $wasPlaying && $isFinished;
    }
    
    private function detectCards(array $previous, array $current): array
    {
        $events     = $current['events'] ?? [];
        $prevEvents = $previous['events'] ?? [];
        $prevKeys   = collect($prevEvents)
            ->where('type', 'Card')
            ->map(fn($e) => $e['time']['elapsed'] . '_' . ($e['player']['name'] ?? ''))
            ->toArray();

        $newCards = collect($events)
            ->where('type', 'Card')
            ->filter(function ($e) use ($prevKeys) {
                $key = $e['time']['elapsed'] . '_' . ($e['player']['name'] ?? '');
                return !in_array($key, $prevKeys, true);
            });

        return $newCards->map(function ($e) {
            $detail = $e['detail'] ?? 'Yellow Card';
            $type   = match (true) {
                $detail === 'Red Card'         => 'red_card',
                $detail === 'Yellow Red Card'  => 'second_yellow',
                default                        => 'yellow_card',
            };
            return [
                'event_type' => $type,
                'player'     => $e['player']['name'] ?? 'Unknown',
                'team'       => $e['team']['name'] ?? 'Unknown',
                'minute'     => $e['time']['elapsed'],
                'reason'     => $e['comments'] ?? $detail,
            ];
        })->values()->toArray();
    }
    
    private function detectSubstitutions(array $previous, array $current): array
    {
        $events = $current['events'] ?? [];
        $prevEvents = $previous['events'] ?? [];
        $prevElapsed = collect($prevEvents)
            ->where('type', 'subst')
            ->pluck('time.elapsed')
            ->toArray();

        return collect($events)
            ->where('type', 'subst')
            ->whereNotIn('time.elapsed', $prevElapsed)
            ->map(fn($e) => [
                'team'      => $e['team']['name'],
                'player_in' => $e['assist']['name'] ?? 'Unknown',
                'player_out'=> $e['player']['name'] ?? 'Unknown',
                'minute'    => $e['time']['elapsed'],
            ])
            ->values()
            ->toArray();
    }

    private function detectPenaltyEvents(array $previous, array $current): array
    {
        $events     = $current['events'] ?? [];
        $prevEvents = $previous['events'] ?? [];
        $prevKeys   = collect($prevEvents)
            ->where('type', 'Goal')
            ->map(fn($e) => $e['time']['elapsed'] . '_' . ($e['player']['name'] ?? ''))
            ->toArray();

        return collect($events)
            ->where('type', 'Goal')
            ->whereIn('detail', ['Missed Penalty'])
            ->filter(function ($e) use ($prevKeys) {
                $key = $e['time']['elapsed'] . '_' . ($e['player']['name'] ?? '');
                return !in_array($key, $prevKeys, true);
            })
            ->map(fn($e) => [
                'event_type' => 'penalty_missed',
                'team'       => $e['team']['name'] ?? 'Unknown',
                'player'     => $e['player']['name'] ?? 'Unknown',
                'minute'     => $e['time']['elapsed'],
            ])
            ->values()
            ->toArray();
    }

    private function detectVarEvents(array $previous, array $current): array
    {
        $events     = $current['events'] ?? [];
        $prevEvents = $previous['events'] ?? [];
        $prevKeys   = collect($prevEvents)
            ->where('type', 'Var')
            ->map(fn($e) => $e['time']['elapsed'] . '_' . ($e['detail'] ?? ''))
            ->toArray();

        return collect($events)
            ->where('type', 'Var')
            ->filter(function ($e) use ($prevKeys) {
                $key = $e['time']['elapsed'] . '_' . ($e['detail'] ?? '');
                return !in_array($key, $prevKeys, true);
            })
            ->map(fn($e) => [
                'detail'  => $e['detail'] ?? 'VAR Review',
                'team'    => $e['team']['name'] ?? 'Unknown',
                'player'  => $e['player']['name'] ?? null,
                'minute'  => $e['time']['elapsed'],
                'comment' => $e['comments'] ?? null,
            ])
            ->values()
            ->toArray();
    }
    
    private function extractGoalEvent(array $match, bool $isHome): ?array
    {
        $teamName = $isHome ? $match['teams']['home']['name'] : $match['teams']['away']['name'];
        return collect($match['events'] ?? [])
            ->where('type', 'Goal')
            ->filter(fn($e) => ($e['team']['name'] ?? '') === $teamName)
            ->sortByDesc('time.elapsed')
            ->first();
    }
    
    private function buildKickoffData(array $match): array
    {
        return [
            'home_team' => $match['teams']['home']['name'],
            'away_team' => $match['teams']['away']['name'],
            'home_logo' => $match['teams']['home']['logo'],
            'away_logo' => $match['teams']['away']['logo'],
            'venue' => $match['fixture']['venue']['name'] ?? 'Unknown venue',
            'stage' => $match['league']['round'] ?? 'Group stage',
        ];
    }
    
    private function buildHalftimeData(array $match): array
    {
        return [
            'home_team' => $match['teams']['home']['name'],
            'away_team' => $match['teams']['away']['name'],
            'home_score' => $match['goals']['home'],
            'away_score' => $match['goals']['away'],
            'possession' => $this->extractStat($match, 'Ball Possession'),
        ];
    }
    
    private function buildFulltimeData(array $match): array
    {
        return [
            'home_team' => $match['teams']['home']['name'],
            'away_team' => $match['teams']['away']['name'],
            'home_score' => $match['goals']['home'],
            'away_score' => $match['goals']['away'],
            'winner' => $this->determineWinner($match),
        ];
    }
    
    private function extractStat(array $match, string $statName): ?array
    {
        $stats = $match['statistics'] ?? [];
        
        $stat = collect($stats)
            ->pluck('statistics')
            ->flatten(1)
            ->firstWhere('type', $statName);
        
        if ($stat) {
            return [
                'home' => $stats[0]['statistics'][$statName] ?? '?',
                'away' => $stats[1]['statistics'][$statName] ?? '?',
            ];
        }
        
        return null;
    }
    
    private function determineWinner(array $match): ?string
    {
        $home = $match['goals']['home'] ?? 0;
        $away = $match['goals']['away'] ?? 0;
        
        if ($home > $away) {
            return $match['teams']['home']['name'];
        } elseif ($away > $home) {
            return $match['teams']['away']['name'];
        }
        return null;
    }
    
    private function storeState(int $matchId, array $match): void
    {
        MatchState::updateOrCreate(
            ['external_match_id' => $matchId],
            [
                'state_snapshot' => $match,
                'last_checked' => now(),
            ]
        );
    }
}
