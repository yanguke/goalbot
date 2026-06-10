<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WorldCupTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // World Cup 2026 Test Matches (June 11-12, 2026)
        $matches = [
            [
                'external_match_id' => 120001,
                'state_snapshot' => json_encode([
                    'fixture' => [
                        'id' => 120001,
                        'date' => '2026-06-11T19:00:00+00:00',
                        'timestamp' => Carbon::parse('2026-06-11 19:00:00')->timestamp,
                        'timezone' => 'UTC',
                        'status' => ['short' => 'NS', 'long' => 'Not Started'],
                        'venue' => ['name' => 'Estadio Azteca', 'city' => 'Mexico City'],
                        'referee' => null,
                    ],
                    'league' => [
                        'id' => 1,
                        'name' => 'World Cup',
                        'season' => 2026,
                        'round' => 'Group Stage - 1',
                    ],
                    'teams' => [
                        'home' => [
                            'id' => 1541,
                            'name' => 'Mexico',
                            'logo' => 'https://media.api-sports.io/football/teams/1541.png',
                            'winner' => null,
                        ],
                        'away' => [
                            'id' => 1531,
                            'name' => 'South Africa',
                            'logo' => 'https://media.api-sports.io/football/teams/1531.png',
                            'winner' => null,
                        ],
                    ],
                    'goals' => ['home' => 0, 'away' => 0],
                    'score' => [
                        'halftime' => ['home' => null, 'away' => null],
                        'fulltime' => ['home' => null, 'away' => null],
                        'extratime' => ['home' => null, 'away' => null],
                        'penalty' => ['home' => null, 'away' => null],
                    ],
                    'events' => [],
                ]),
                'last_checked' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_match_id' => 120002,
                'state_snapshot' => json_encode([
                    'fixture' => [
                        'id' => 120002,
                        'date' => '2026-06-11T22:00:00+00:00',
                        'timestamp' => Carbon::parse('2026-06-11 22:00:00')->timestamp,
                        'timezone' => 'UTC',
                        'status' => ['short' => 'NS', 'long' => 'Not Started'],
                        'venue' => ['name' => 'AT&T Stadium', 'city' => 'Arlington'],
                        'referee' => null,
                    ],
                    'league' => [
                        'id' => 1,
                        'name' => 'World Cup',
                        'season' => 2026,
                        'round' => 'Group Stage - 1',
                    ],
                    'teams' => [
                        'home' => [
                            'id' => 6,
                            'name' => 'Brazil',
                            'logo' => 'https://media.api-sports.io/football/teams/6.png',
                            'winner' => null,
                        ],
                        'away' => [
                            'id' => 14,
                            'name' => 'Germany',
                            'logo' => 'https://media.api-sports.io/football/teams/14.png',
                            'winner' => null,
                        ],
                    ],
                    'goals' => ['home' => 0, 'away' => 0],
                    'score' => [
                        'halftime' => ['home' => null, 'away' => null],
                        'fulltime' => ['home' => null, 'away' => null],
                        'extratime' => ['home' => null, 'away' => null],
                        'penalty' => ['home' => null, 'away' => null],
                    ],
                    'events' => [],
                ]),
                'last_checked' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_match_id' => 120003,
                'state_snapshot' => json_encode([
                    'fixture' => [
                        'id' => 120003,
                        'date' => '2026-06-12T16:00:00+00:00',
                        'timestamp' => Carbon::parse('2026-06-12 16:00:00')->timestamp,
                        'timezone' => 'UTC',
                        'status' => ['short' => 'NS', 'long' => 'Not Started'],
                        'venue' => ['name' => 'BC Place', 'city' => 'Vancouver'],
                        'referee' => null,
                    ],
                    'league' => [
                        'id' => 1,
                        'name' => 'World Cup',
                        'season' => 2026,
                        'round' => 'Group Stage - 1',
                    ],
                    'teams' => [
                        'home' => [
                            'id' => 2,
                            'name' => 'France',
                            'logo' => 'https://media.api-sports.io/football/teams/2.png',
                            'winner' => null,
                        ],
                        'away' => [
                            'id' => 26,
                            'name' => 'Argentina',
                            'logo' => 'https://media.api-sports.io/football/teams/26.png',
                            'winner' => null,
                        ],
                    ],
                    'goals' => ['home' => 0, 'away' => 0],
                    'score' => [
                        'halftime' => ['home' => null, 'away' => null],
                        'fulltime' => ['home' => null, 'away' => null],
                        'extratime' => ['home' => null, 'away' => null],
                        'penalty' => ['home' => null, 'away' => null],
                    ],
                    'events' => [],
                ]),
                'last_checked' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('match_states')->insert($matches);

        $this->command->info('✅ World Cup 2026 test matches seeded!');
        $this->command->info('   - Mexico vs South Africa (June 11, 19:00 UTC)');
        $this->command->info('   - Brazil vs Germany (June 11, 22:00 UTC)');
        $this->command->info('   - France vs Argentina (June 12, 16:00 UTC)');
    }
}
