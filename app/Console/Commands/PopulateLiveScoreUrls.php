<?php

namespace App\Console\Commands;

use App\Models\LiveScoreCommentaryUrl;
use App\Services\Football\FootballDataService;
use App\Services\Football\LiveScoreCommentaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PopulateLiveScoreUrls extends Command
{
    protected $signature = 'livescore:populate-urls {--verify : Verify each URL works}';
    protected $description = 'Populate LiveScore commentary URLs for all World Cup 2026 fixtures';

    public function handle(FootballDataService $football, LiveScoreCommentaryService $commentary): int
    {
        $this->info('Populating LiveScore commentary URLs for World Cup 2026...');

        // Get all World Cup 2026 fixtures
        $fixtures = $football->getWorldCupFixtures();
        $this->info('Found ' . count($fixtures) . ' fixtures');

        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($fixtures as $fixture) {
            $fixtureId = $fixture['fixture']['id'];
            $homeTeam = $fixture['teams']['home']['name'];
            $awayTeam = $fixture['teams']['away']['name'];

            $this->line("Processing: {$homeTeam} vs {$awayTeam} (ID: {$fixtureId})");

            // Try to discover the LiveScore slug
            $slug = $commentary->discoverSlug($homeTeam, $awayTeam, $fixtureId);

            if ($slug) {
                $verified = false;
                $verifiedAt = null;

                // Optional verification
                if ($this->option('verify')) {
                    $this->line('  Verifying URL...');
                    $verified = $this->verifyUrl($slug);
                    $verifiedAt = $verified ? now() : null;
                    $this->line('  ' . ($verified ? '✓ Verified' : '✗ Failed verification'));
                }

                // Create or update record
                LiveScoreCommentaryUrl::updateOrCreate(
                    ['fixture_id' => $fixtureId],
                    [
                        'home_team' => $homeTeam,
                        'away_team' => $awayTeam,
                        'livescore_slug' => $slug,
                        'verified' => $verified,
                        'verified_at' => $verifiedAt,
                    ]
                );

                if (LiveScoreCommentaryUrl::where('fixture_id', $fixtureId)->where('created_at', '>', now()->subMinutes(1))->exists()) {
                    $created++;
                } else {
                    $updated++;
                }

                $this->info("  ✓ Saved: {$slug}");
            } else {
                $this->error("  ✗ Failed to discover slug");
                $failed++;
            }

            // Small delay to avoid overwhelming LiveScore
            usleep(500000);
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Created: {$created}");
        $this->info("  Updated: {$updated}");
        $this->info("  Failed: {$failed}");

        return self::SUCCESS;
    }

    private function verifyUrl(string $slug): bool
    {
        try {
            $url = "https://www.livescore.com/en/football/international/world-cup-2026/{$slug}/";
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'en-US,en;q=0.5',
            ];
            
            $response = Http::withHeaders($headers)->timeout(5)->get($url);
            return $response->successful() && str_contains($response->body(), 'commentary');
        } catch (\Exception $e) {
            Log::warning('URL verification failed', ['slug' => $slug, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
