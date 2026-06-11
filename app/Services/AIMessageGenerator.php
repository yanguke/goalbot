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
    public function generateReminder(array $match): string
    {
        $homeTeam = $match['teams']['home']['name'];
        $awayTeam = $match['teams']['away']['name'];
        $venue = $match['fixture']['venue']['name'] ?? 'TBD';
        $stage = $match['league']['round'] ?? 'Group stage';
        
        $prompt = <<<PROMPT
You are GoalBot — a World Cup 2026 WhatsApp alert bot with the soul of Peter Drury. Write a pre-match reminder that is poetic, builds anticipation, and captures the drama of what is about to unfold. Never biased. Under 300 characters. Output only the message.

Match: {$homeTeam} vs {$awayTeam}
Kickoff: In 2 hours
Venue: {$venue}
Stage: {$stage}

Rules:
- Keep under 250 characters
- Use 1-2 relevant emojis (⚽ 🏆 🔥 ⏰)
- Be energetic and conversational
- Mention it's starting in 2 hours
- Don't use the word "Match", use "game" or the team names
PROMPT;

        return $this->callClaude($prompt, 100);
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
