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
        
        // Check for various events
        if ($this->isKickoff($previous, $currentMatch)) {
            $events[] = [
                'type' => 'kickoff',
                'data' => $this->buildKickoffData($currentMatch),
            ];
        }
        
        if ($goalData = $this->detectGoal($previous, $currentMatch)) {
            $events[] = [
                'type' => 'goal',
                'data' => $goalData,
            ];
        }
        
        if ($this->isHalfTime($previous, $currentMatch)) {
            $events[] = [
                'type' => 'halftime',
                'data' => $this->buildHalftimeData($currentMatch),
            ];
        }
        
        if ($this->isFullTime($previous, $currentMatch)) {
            $events[] = [
                'type' => 'fulltime',
                'data' => $this->buildFulltimeData($currentMatch),
            ];
        }
        
        if ($cardData = $this->detectRedCard($previous, $currentMatch)) {
            $events[] = [
                'type' => 'red_card',
                'data' => $cardData,
            ];
        }
        
        if ($penaltyData = $this->detectPenalty($previous, $currentMatch)) {
            $events[] = [
                'type' => 'penalty',
                'data' => $penaltyData,
            ];
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
    
    private function detectGoal(array $previous, array $current): ?array
    {
        $prevHome = $previous['goals']['home'] ?? 0;
        $prevAway = $previous['goals']['away'] ?? 0;
        $currHome = $current['goals']['home'] ?? 0;
        $currAway = $current['goals']['away'] ?? 0;
        
        if ($currHome > $prevHome) {
            return [
                'team' => $current['teams']['home']['name'],
                'team_logo' => $current['teams']['home']['logo'],
                'is_home' => true,
                'score' => "{$currHome}-{$currAway}",
                'minute' => $current['fixture']['status']['elapsed'] ?? '?',
                'scorer' => $this->extractScorer($current, true),
            ];
        }
        
        if ($currAway > $prevAway) {
            return [
                'team' => $current['teams']['away']['name'],
                'team_logo' => $current['teams']['away']['logo'],
                'is_home' => false,
                'score' => "{$currHome}-{$currAway}",
                'minute' => $current['fixture']['status']['elapsed'] ?? '?',
                'scorer' => $this->extractScorer($current, false),
            ];
        }
        
        return null;
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
    
    private function detectRedCard(array $previous, array $current): ?array
    {
        // Get events from API if available
        $events = $current['events'] ?? [];
        $prevEvents = $previous['events'] ?? [];
        
        $newRedCards = collect($events)
            ->where('type', 'Card')
            ->where('detail', 'Red Card')
            ->whereNotIn('time.elapsed', collect($prevEvents)->pluck('time.elapsed')->toArray())
            ->first();
        
        if ($newRedCards) {
            return [
                'player' => $newRedCards['player']['name'],
                'team' => $newRedCards['team']['name'],
                'minute' => $newRedCards['time']['elapsed'],
                'reason' => 'Red card',
            ];
        }
        
        return null;
    }
    
    private function detectPenalty(array $previous, array $current): ?array
    {
        $events = $current['events'] ?? [];
        $prevEvents = $previous['events'] ?? [];
        
        $newPenalty = collect($events)
            ->where('type', 'Card')
            ->where('comments', 'Penalty')
            ->whereNotIn('time.elapsed', collect($prevEvents)->pluck('time.elapsed')->toArray())
            ->first();
        
        if ($newPenalty) {
            return [
                'team' => $newPenalty['team']['name'],
                'player' => $newPenalty['player']['name'],
                'minute' => $newPenalty['time']['elapsed'],
            ];
        }
        
        return null;
    }
    
    private function extractScorer(array $match, bool $isHome): ?string
    {
        $events = $match['events'] ?? [];
        
        $goalEvent = collect($events)
            ->where('type', 'Goal')
            ->where('team.name', $isHome ? $match['teams']['home']['name'] : $match['teams']['away']['name'])
            ->sortByDesc('time.elapsed')
            ->first();
        
        return $goalEvent['player']['name'] ?? null;
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
