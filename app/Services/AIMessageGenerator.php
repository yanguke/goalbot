<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIMessageGenerator
{
    private string $apiKey;
    private string $model;
    
    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key') ?? '';
        $this->model = config('services.anthropic.model', 'claude-haiku-4-5');
    }
    
    /**
     * Generate a message for a specific event
     */
    public function generate(string $eventType, array $data, ?string $userTeam = null): string
    {
        $cacheKey = $this->buildCacheKey($eventType, $data, $userTeam);
        
        // Cache generated messages to reduce API calls and cost
        return Cache::remember($cacheKey, 300, function () use ($eventType, $data, $userTeam) {
            return $this->callAI($eventType, $data, $userTeam);
        });
    }
    
    /**
     * Generate a reminder message for upcoming match
     */
    public function generateReminder(array $match, string $windowLabel = '1 hour'): string
    {
        $homeTeam = $match['teams']['home']['name'];
        $awayTeam = $match['teams']['away']['name'];
        $venue = $match['fixture']['venue']['name'] ?? 'TBD';
        $stage = $match['league']['round'] ?? 'Group stage';

        $cacheKey = 'reminder_msg_' . md5("{$homeTeam}_{$awayTeam}_{$windowLabel}");
        return Cache::remember($cacheKey, 7200, function () use ($homeTeam, $awayTeam, $venue, $stage, $windowLabel) {
            $prompt = <<<PROMPT
You are GoalBot — a World Cup 2026 WhatsApp alert bot with the soul of Peter Drury. Write a pre-match reminder that is poetic, builds anticipation, and captures the drama of what is about to unfold. Never biased. Output only the message.

Match: {$homeTeam} vs {$awayTeam}
Kickoff: In {$windowLabel}
Venue: {$venue}
Stage: {$stage}

Rules:
- Keep under 250 characters
- Use 1-2 relevant emojis (⚽ 🏆 🔥 ⏰)
- Be energetic and match the urgency of the time window (e.g. "5 minutes" should feel electric, "2 hours" should build anticipation)
- Mention exactly how long until kickoff: "{$windowLabel}"
- Don't use the word "Match", use "game" or the team names
PROMPT;
            return $this->callClaude($prompt, 100);
        });
    }
    
    private function callAI(string $eventType, array $data, ?string $userTeam): string
    {
        $prompt = $this->buildPrompt($eventType, $data, $userTeam);
        return $this->callClaude($prompt, 150);
    }
    
    private function buildPrompt(string $type, array $data, ?string $userTeam): string
    {
        $teamContext = $userTeam ? "User supports: {$userTeam}. If it is their team, channel Peter Drury at his most electric — but stay factual and fair." : "";
        $scorer = $data['scorer'] ?? 'Unknown player';
        $isUserTeam = ($userTeam && isset($data['team']) && $data['team'] === $userTeam) ? 'YES' : 'NO';
        $winner = $data['winner'] ?? 'Draw';
        
        if ($type === 'kickoff') {
            return "{$teamContext}\nEvent: KICKOFF - Game is starting NOW!\nTeams: {$data['home_team']} vs {$data['away_team']}\nStage: {$data['stage']}\nVenue: {$data['venue']}\n\nRules:\n- Start with 🔴 LIVE or ⚽ KICKOFF\n- Keep under 200 characters\n- Be energetic\n- Mention the venue\n- Don't say 'The match has started', be more exciting";
        }
        
        if ($type === 'goal') {
            $goalType = $data['goal_type'] ?? 'Normal Goal';
            $assist   = $data['assist'] ?? null;
            $assistStr = $assist ? "Assist: {$assist}" : 'No assist data';
            $ownGoal  = ($data['is_own_goal'] ?? false) ? 'YES — own goal, credit goes against scoring team' : 'NO';
            $isPen    = ($data['is_penalty'] ?? false) ? 'YES — penalty' : 'NO';
            return "{$teamContext}\nEvent: GOAL!\nScoring team: {$data['team']}\nScore: {$data['score']}\nScorer: {$scorer}\n{$assistStr}\nMinute: {$data['minute']}'\nGoal type: {$goalType}\nOwn goal? {$ownGoal}\nPenalty? {$isPen}\nIs user's team? {$isUserTeam}\n\nRules:\n- Own goal: use 😬 and show sympathy/drama\n- Penalty: use 🎯 ⚽\n- Normal goal: use ⚽ GOAL!\n- Include scorer and minute\n- Mention assist if available\n- Be poetic and dramatic\n- Under 250 characters";
        }
        
        if ($type === 'halftime') {
            return "{$teamContext}\nEvent: HALF-TIME\nScore: {$data['home_score']}-{$data['away_score']}\n{$data['home_team']} vs {$data['away_team']}\n\nRules:\n- Start with ⏸️ HALF-TIME\n- Keep under 200 characters\n- Brief summary of first half\n- Mention who leads or if it's level";
        }
        
        if ($type === 'fulltime') {
            return "{$teamContext}\nEvent: FULL TIME - Game over!\nFinal score: {$data['home_score']}-{$data['away_score']}\n{$data['home_team']} vs {$data['away_team']}\nWinner: {$winner}\n\nRules:\n- Start with 🏁 FULL TIME\n- Keep under 200 characters\n- Announce winner clearly\n- Note if it was a draw\n- Mention implications if obvious";
        }
        
        if ($type === 'red_card') {
            return "{$teamContext}\nEvent: RED CARD!\nPlayer: {$data['player']}\nTeam: {$data['team']}\nMinute: {$data['minute']}'\n\nRules:\n- Start with 🟥 RED CARD!\n- Keep under 150 characters\n- Dramatic tone\n- Note the impact (team down to 10 men)";
        }
        
        if ($type === 'penalty') {
            return "{$teamContext}\nEvent: PENALTY awarded!\nTeam: {$data['team']}\nPlayer: {$data['player']}\nMinute: {$data['minute']}'\n\nRules:\n- Start with 🎯 PENALTY!\n- Keep under 150 characters\n- Dramatic moment\n- Don't say who will take it";
        }

        if ($type === 'substitution') {
            return "{$teamContext}\nEvent: SUBSTITUTION\nTeam: {$data['team']}\nOFF: {$data['player_out']}\nON: {$data['player_in']}\nMinute: {$data['minute']}'\n\nRules:\n- Start with 🔄 SUB\n- Keep under 120 characters\n- Format: Team | PlayerOUT ➡ PlayerIN | Minute\n- One brief tactical note if obvious";
        }

        if ($type === 'yellow_card') {
            return "{$teamContext}\nEvent: YELLOW CARD\nPlayer: {$data['player']}\nTeam: {$data['team']}\nMinute: {$data['minute']}'\nReason: {$data['reason']}\n\nRules:\n- Start with 🟨\n- Keep under 100 characters\n- Note it's a booking, brief";
        }

        if ($type === 'second_yellow') {
            return "{$teamContext}\nEvent: SECOND YELLOW = RED CARD\nPlayer: {$data['player']}\nTeam: {$data['team']}\nMinute: {$data['minute']}'\n\nRules:\n- Start with 🟨🟥 OFF!\n- Keep under 150 characters\n- Dramatic — team down to 10 men\n- This is a game-changer moment";
        }

        if ($type === 'penalty_missed') {
            return "{$teamContext}\nEvent: PENALTY MISSED!\nPlayer: {$data['player']}\nTeam: {$data['team']}\nMinute: {$data['minute']}'\n\nRules:\n- Start with 😱 MISSED!\n- Keep under 150 characters\n- Capture the agony\n- Peter Drury at his most dramatic";
        }

        if ($type === 'var') {
            $detail  = $data['detail'] ?? 'VAR Review';
            $player  = $data['player'] ?? '';
            $comment = $data['comment'] ?? '';
            return "{$teamContext}\nEvent: VAR — {$detail}\nTeam: {$data['team']}\nPlayer: {$player}\nMinute: {$data['minute']}'\nComment: {$comment}\n\nRules:\n- Start with 📺 VAR\n- Keep under 150 characters\n- Describe what VAR decided (goal cancelled, penalty confirmed etc.)\n- Capture the tension of the wait";
        }

        if ($type === 'second_half') {
            return "{$teamContext}\nEvent: SECOND HALF UNDERWAY\nScore: {$data['home_score']}-{$data['away_score']}\n{$data['home_team']} vs {$data['away_team']}\n\nRules:\n- Start with ⚽ 2ND HALF\n- Keep under 120 characters\n- Build anticipation for the second half\n- Mention current score";
        }

        return "Generate a WhatsApp message for a World Cup {$type} event with data: " . json_encode($data);
    }
    
    private function callClaude(string $prompt, int $maxTokens): string
    {
        if (empty($this->apiKey)) {
            Log::warning('Claude API key not configured, using fallback');
            return $this->fallbackMessage($prompt);
        }
        
        try {
            $response = Http::timeout(10)->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => 'You are GoalBot — a World Cup 2026 WhatsApp alert bot with the soul of Peter Drury. Your commentary is poetic, dramatic, and rich with metaphor. You find the humanity and theatre in every moment. You are never biased — you celebrate the game itself. Keep messages under 300 characters, use 1-2 emojis, and output only the message with no preamble or explanation.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.8,
            ]);
            
            if ($response->successful()) {
                return trim($response->json('content.0.text', 'GoalBot update! ⚽'));
            }
            
            Log::error('Claude API error', [
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? $response->body(),
            ]);
            
            return $this->fallbackMessage($prompt);
            
        } catch (\Exception $e) {
            Log::error('Claude exception', ['error' => $e->getMessage()]);
            return $this->fallbackMessage($prompt);
        }
    }
    
    /**
     * Fallback message generator when AI is unavailable
     */
    private function fallbackMessage(string $prompt): string
    {
        // Extract event type from prompt
        if (str_contains($prompt, 'GOAL')) {
            preg_match('/Scoring team: ([^\n]+)/', $prompt, $team);
            preg_match('/Score: ([^\n]+)/', $prompt, $score);
            preg_match('/Scorer: ([^\n]+)/', $prompt, $scorer);
            preg_match('/Minute: ([^\n]+)/', $prompt, $minute);
            
            return "⚽ GOAL! " . ($team[1] ?? 'Team') . " score! " . ($score[1] ?? '') . " - " . ($scorer[1] ?? 'Player') . " " . ($minute[1] ?? '') . "' 🔥";
        }
        
        if (str_contains($prompt, 'KICKOFF')) {
            preg_match('/Teams: ([^\n]+)/', $prompt, $teams);
            return "🔴 LIVE! " . ($teams[1] ?? 'Match') . " has kicked off! ⚽";
        }
        
        if (str_contains($prompt, 'HALF-TIME')) {
            preg_match('/Score: ([^\n]+)/', $prompt, $score);
            return "⏸️ HALF-TIME: Score is " . ($score[1] ?? 'level') . ". Second half coming up!";
        }
        
        if (str_contains($prompt, 'FULL TIME')) {
            preg_match('/Winner: ([^\n]+)/', $prompt, $winner);
            return "🏁 FULL TIME! " . ($winner[1] ?? 'Match') . " ends! " . (str_contains($winner[1] ?? '', 'Draw') ? 'What a game!' : '');
        }
        
        if (str_contains($prompt, 'RED CARD')) {
            return "🟥 RED CARD! Game changing moment! ⚠️";
        }
        
        return "🏆 World Cup update! Stay tuned for more action! ⚽";
    }
    
    /**
     * Generate a rich pre-match briefing for the 1-hour reminder window.
     * Includes H2H, form, lineups (if available), key players, odds, injuries.
     */
    public function generatePreMatchBriefing(array $match, array $context = []): string
    {
        if (empty($this->apiKey)) return '';

        $home    = $match['teams']['home']['name'];
        $away    = $match['teams']['away']['name'];
        $venue   = $match['fixture']['venue']['name'] ?? 'TBD';
        $round   = $match['league']['round'] ?? 'World Cup 2026';
        $homeId  = $match['teams']['home']['id'];
        $awayId  = $match['teams']['away']['id'];
        $fId     = $match['fixture']['id'];

        $football = app(\App\Services\Football\FootballDataService::class);

        // Gather all data
        $h2h       = $football->getHeadToHead($homeId, $awayId);
        $homeForm  = $football->getTeamForm($homeId, 5);
        $awayForm  = $football->getTeamForm($awayId, 5);
        $lineups   = $football->getLineups($fId);
        $odds      = $football->getOdds($fId);
        $injuries  = $football->getInjuries($fId);
        $preds     = $football->getPredictions($fId);

        // Build H2H summary
        $h2hSummary = '';
        if (!empty($h2h)) {
            $homeWins = $awayWins = $draws = 0;
            foreach (array_slice($h2h, 0, 10) as $game) {
                $hg = $game['goals']['home'] ?? 0;
                $ag = $game['goals']['away'] ?? 0;
                $gh = $game['teams']['home']['id'];
                if ($hg > $ag) { $gh === $homeId ? $homeWins++ : $awayWins++; }
                elseif ($hg < $ag) { $gh === $homeId ? $awayWins++ : $homeWins++; }
                else $draws++;
            }
            $h2hSummary = "Last " . count(array_slice($h2h, 0, 10)) . " meetings: {$home} {$homeWins}W | Draws {$draws} | {$away} {$awayWins}W";
        }

        // Form strings (W/D/L)
        $formStr = function (array $results, int $teamId) {
            return collect($results)->map(function ($g) use ($teamId) {
                $hg = $g['goals']['home'] ?? 0;
                $ag = $g['goals']['away'] ?? 0;
                $isHome = $g['teams']['home']['id'] === $teamId;
                $scored = $isHome ? $hg : $ag;
                $conceded = $isHome ? $ag : $hg;
                if ($scored > $conceded) return 'W';
                if ($scored < $conceded) return 'L';
                return 'D';
            })->implode(' ');
        };
        $homeFormStr = $homeForm ? $formStr($homeForm, $homeId) : 'N/A';
        $awayFormStr = $awayForm ? $formStr($awayForm, $awayId) : 'N/A';

        // Lineups summary
        $lineupStr = '';
        if (!empty($lineups)) {
            foreach ($lineups as $team) {
                $tName = $team['team']['name'];
                $formation = $team['formation'] ?? '?';
                $starters = collect($team['startXI'] ?? [])->map(fn($p) => $p['player']['name'])->implode(', ');
                $lineupStr .= "{$tName} [{$formation}]: {$starters}\n";
            }
        }

        // Odds
        $oddsStr = '';
        $mw = $odds['bets']['Match Winner'] ?? [];
        if ($mw) {
            $oddsStr = "{$home} " . ($mw['Home'] ?? '?') . " | Draw " . ($mw['Draw'] ?? '?') . " | {$away} " . ($mw['Away'] ?? '?');
        }

        // Injuries
        $injStr = '';
        if (!empty($injuries)) {
            $injStr = collect($injuries)->map(fn($i) => $i['team']['name'] . ': ' . $i['player']['name'] . ' (' . $i['player']['type'] . ')')->implode(', ');
        }

        // Prediction
        $predStr = '';
        if ($preds) {
            $winner  = $preds['predictions']['winner']['name'] ?? null;
            $advice  = $preds['predictions']['advice'] ?? null;
            $pct     = $preds['predictions']['percent'] ?? [];
            if ($winner) $predStr .= "Predicted winner: {$winner}. ";
            if ($advice) $predStr .= $advice . ". ";
            if ($pct) $predStr .= "Win%: {$home} {$pct['home']} / Draw {$pct['draw']} / {$away} {$pct['away']}";
        }

        $cacheKey = 'prematch_briefing_' . md5("{$home}_{$away}_{$fId}");
        return Cache::remember($cacheKey, 3600, function () use (
            $home, $away, $venue, $round, $h2hSummary, $homeFormStr, $awayFormStr,
            $lineupStr, $oddsStr, $injStr, $predStr
        ) {
            try {
                $dataBlock = implode("\n", array_filter([
                    $h2hSummary ? "H2H: {$h2hSummary}" : null,
                    "Form (last 5): {$home}: {$homeFormStr} | {$away}: {$awayFormStr}",
                    $lineupStr ? "Lineups:\n{$lineupStr}" : null,
                    $oddsStr ? "Odds: {$oddsStr}" : null,
                    $injStr ? "Injuries/Suspensions: {$injStr}" : null,
                    $predStr ? "AI Prediction: {$predStr}" : null,
                ]));

                $response = Http::timeout(20)->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => 600,
                    'system'     => 'You are GoalBot — a World Cup 2026 AI companion with the soul of Peter Drury. Write a pre-match briefing that is dramatic, insightful and gets the fan genuinely excited. Use real data provided. WhatsApp format — bold with *asterisks*, max 700 characters, 3-4 emojis. No markdown headers.',
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => "Write a pre-match briefing for: *{$home} vs {$away}*\nVenue: {$venue} | {$round}\n\nData:\n{$dataBlock}\n\nInclude: form, one H2H fact, key player to watch, your predicted score. End with hype.",
                    ]],
                    'temperature' => 0.85,
                ]);

                return $response->successful()
                    ? "📋 *Pre-Match Briefing*\n\n" . trim($response->json('content.0.text', ''))
                    : '';
            } catch (\Exception $e) {
                Log::warning('Pre-match briefing failed', ['error' => $e->getMessage()]);
                return '';
            }
        });
    }

    /**
     * Generate a post-match summary — score, report, group impact.
     */
    public function generatePostMatchSummary(array $match, array $events, array $standings = []): string
    {
        if (empty($this->apiKey)) return '';

        $home    = $match['teams']['home']['name'];
        $away    = $match['teams']['away']['name'];
        $hGoals  = $match['goals']['home'] ?? 0;
        $aGoals  = $match['goals']['away'] ?? 0;
        $venue   = $match['fixture']['venue']['name'] ?? '';
        $round   = $match['league']['round'] ?? 'World Cup 2026';

        // Build goals list
        $goalLines = collect($events)
            ->filter(fn($e) => $e['type'] === 'Goal')
            ->map(function ($e) {
                $min    = $e['time']['elapsed'] ?? '?';
                $player = $e['player']['name'] ?? '?';
                $team   = $e['team']['name'] ?? '?';
                $detail = strtolower($e['detail'] ?? '');
                $tag    = str_contains($detail, 'own') ? ' (OG)' : (str_contains($detail, 'penalty') ? ' (pen)' : '');
                return "  {$min}' {$player}{$tag} ({$team})";
            })->implode("\n");

        // Group standing snippet (just the relevant group)
        $standingStr = '';
        foreach ($standings as $group) {
            $teams = collect($group)->pluck('team.name')->toArray();
            if (in_array($home, $teams) || in_array($away, $teams)) {
                $standingStr = collect($group)->map(fn($e) =>
                    $e['rank'] . '. ' . $e['team']['name'] . ' — ' . $e['points'] . 'pts'
                )->implode(' | ');
                break;
            }
        }

        $cacheKey = 'postmatch_' . md5("{$home}_{$away}_{$hGoals}_{$aGoals}");
        return Cache::remember($cacheKey, 86400, function () use (
            $home, $away, $hGoals, $aGoals, $venue, $round, $goalLines, $standingStr
        ) {
            try {
                $result = $hGoals > $aGoals ? "{$home} win" : ($hGoals < $aGoals ? "{$away} win" : "Draw");
                $dataBlock = "Result: {$home} {$hGoals}-{$aGoals} {$away} ({$result})\n"
                    . "Venue: {$venue} | {$round}\n"
                    . ($goalLines ? "Goals:\n{$goalLines}\n" : "No goals scored.\n")
                    . ($standingStr ? "Group standings: {$standingStr}" : '');

                $response = Http::timeout(20)->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => 500,
                    'system'     => 'You are GoalBot — a World Cup 2026 AI companion with the soul of Peter Drury. Write a post-match summary that is vivid, emotional and captures the drama. WhatsApp format — use *bold*, max 600 characters, 2-3 emojis. No markdown headers. End with one line on what this result means for the group.',
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => "Write a post-match summary:\n\n{$dataBlock}\n\nInclude: result verdict, standout moment, man of the match pick, group implications.",
                    ]],
                    'temperature' => 0.85,
                ]);

                return $response->successful()
                    ? trim($response->json('content.0.text', ''))
                    : '';
            } catch (\Exception $e) {
                Log::warning('Post-match summary failed', ['error' => $e->getMessage()]);
                return '';
            }
        });
    }

    /**
     * Generate a short AI narrative of the last 5 minutes for digest subscribers.
     */
    public function generateDigestNarrative(
        string $home, string $away, int $hGoals, int $aGoals, int $elapsed, string $eventLines
    ): string {
        if (empty($this->apiKey) || empty($eventLines)) {
            return $eventLines;
        }

        $cacheKey = 'digest_narrative_' . md5("{$home}_{$away}_{$elapsed}_{$eventLines}");
        return Cache::remember($cacheKey, 3600, function () use ($home, $away, $hGoals, $aGoals, $elapsed, $eventLines) {
            try {
                $response = Http::timeout(15)->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => 200,
                    'system'     => 'You are GoalBot with the soul of Peter Drury. Write ONE tight, dramatic sentence summarizing the last 5 minutes of a football match. Present tense, vivid, under 250 characters. No markdown, no headers. Output only the sentence.',
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => "Match: {$home} {$hGoals}–{$aGoals} {$away} at minute {$elapsed}\n\nEvents in the last 5 minutes:\n{$eventLines}\n\nWrite ONE tight sentence summarizing what just happened.",
                    ]],
                    'temperature' => 0.9,
                ]);
                return $response->successful()
                    ? trim($response->json('content.0.text', $eventLines))
                    : $eventLines;
            } catch (\Exception $e) {
                return $eventLines;
            }
        });
    }

    private function buildCacheKey(string $eventType, array $data, ?string $userTeam): string
    {
        // Create a unique but stable cache key
        $keyData = [
            'type' => $eventType,
            'team' => $data['team'] ?? ($data['home_team'] ?? ''),
            'score' => $data['score'] ?? '',
            'minute' => $data['minute'] ?? '',
            'user_team' => $userTeam ?? 'none',
        ];
        
        return 'ai_msg_' . md5(json_encode($keyData));
    }
}
