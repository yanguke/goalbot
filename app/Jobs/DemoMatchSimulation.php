<?php

namespace App\Jobs;

use App\Models\Subscriber;
use App\Services\WhatsApp\MessageSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DemoMatchSimulation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Subscriber $subscriber;

    protected const MATCH_EVENTS = [
        [
            'minute' => -60,
            'emoji' => '⏰',
            'type' => 'upcoming',
            'title' => 'Match Starting Soon',
            'message' => "Mexico 🇲🇽 vs South Africa 🇿🇦 kicks off in 60 minutes!\n\n📍 Estadio Azteca, Mexico City\n🏆 World Cup 2026 Opening Match\n\nLineups will be available 30 minutes before kickoff."
        ],
        [
            'minute' => -30,
            'emoji' => '📋',
            'type' => 'lineup',
            'title' => 'Lineups Available',
            'message' => "*Starting XI*\n\n🇲🇽 Mexico:\nOchoa; Araujo, Montes, Vasquez, Gallardo; Alvarez, Herrera, Chavez; Lozano, Jimenez, Antuna\n\n🇿🇦 South Africa:\nWilliams; Mobbie, Xulu, Mvala, Mashego; Mokoena, Sithole; Zwane, Tau, Mayambela; Foster\n\n🔄 Both teams at full strength!"
        ],
        [
            'minute' => -10,
            'emoji' => '🔥',
            'type' => 'warmup',
            'title' => 'Players Warming Up',
            'message' => "Players are on the pitch warming up!\n\n⚽ Lozano and Tau looking sharp\n📊 Weather: 24°C, clear skies\n👥 Stadium at 105,000 capacity\n\n*Kickoff in 10 minutes* 🕒"
        ],
        [
            'minute' => 0,
            'emoji' => '🔴',
            'type' => 'kickoff',
            'title' => 'KICKOFF!',
            'message' => "*The World Cup 2026 is LIVE!* 🏆\n\nMexico 🇲🇽 0-0 🇿🇦 South Africa\n\n🎙️ \"The greatest show on earth begins! Mexico hosts the opener at the iconic Estadio Azteca.\"\n\n*1st minute* ⏱️"
        ],
        [
            'minute' => 12,
            'emoji' => '⚡',
            'type' => 'action',
            'title' => '12\' - Close Chance!',
            'message' => "Nearly!\n\n🇿🇦 Zwane breaks through on the right, cuts inside... fires just wide of the post!\n\n⚡ Electric pace from the South African winger\n📊 xG: 0.08 (Mexico 0.03 - 0.11 South Africa)"
        ],
        [
            'minute' => 23,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => '23\' - GOAL!',
            'message' => "*GOAL! MEXICO 1-0!* ⚽\n\n🇲🇽 LOZANO SCORES!\n\n🎯 Magical free-kick curls over the wall and into the top corner!\n\n📊 Mexico dominant in possession (58%)\n\n*Mexico 🇲🇽 1-0 🇿🇦 South Africa*"
        ],
        [
            'minute' => 31,
            'emoji' => '🛑',
            'type' => 'yellow',
            'title' => '31\' - Yellow Card',
            'message' => "🟨 Yellow Card\n\n🇿🇦 Sithole booked for a late challenge on Lozano\n\n*South Africa struggling to contain the Mexican midfield*"
        ],
        [
            'minute' => 38,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => '38\' - EQUALIZER!',
            'message' => "*GOAL! SOUTH AFRICA 1-1!* ⚽\n\n🇿🇦 TAU EQUALIZES!\n\n💨 Lightning counter-attack! Zwane crosses, Tau smashes home from 8 yards\n\n🔄 Game on! South Africa back in it\n\n*Mexico 🇲🇽 1-1 🇿🇦 South Africa*"
        ],
        [
            'minute' => 45,
            'emoji' => '⏱️',
            'type' => 'halftime',
            'title' => 'HALF TIME',
            'message' => "*HALF TIME* ⏱️\n\n🇲🇽 Mexico 1-1 South Africa 🇿🇦\n\n⚽ Goals: Lozano (23\'), Tau (38\')\n\n📊 Stats:\n• Possession: 55%-45%\n• Shots: 7-5\n• xG: 0.72-0.68\n\n🔥 Intense first half!\n\n*Second half in 15 minutes*"
        ],
        [
            'minute' => 60,
            'emoji' => '🔁',
            'type' => 'substitution',
            'title' => '60\' - Substitutions',
            'message' => "🔄 Double Sub\n\n🇲🇽 Mexico: Martin ⬆️ replaces Jimenez ⬇️\n🇿🇦 South Africa: Maboe ⬆️ replaces Mayambela ⬇️\n\nBoth managers looking for fresh legs in midfield\n\n*Mexico 🇲🇽 1-1 🇿🇦 South Africa*"
        ],
        [
            'minute' => 67,
            'emoji' => '🟥',
            'type' => 'redcard',
            'title' => '67\' - RED CARD!',
            'message' => "*RED CARD!* 🟥\n\n🇿🇦 Mokoena sent off!\n\n🛑 Professional foul on Lozano as last man - clear denial of goal-scoring opportunity\n\n📊 South Africa down to 10 men with 23 minutes + stoppage remaining!\n\n*Mexico 🇲🇽 1-1 🇿🇦 South Africa*"
        ],
        [
            'minute' => 75,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => '75\' - GOAL!',
            'message' => "*GOAL! MEXICO 2-1!* ⚽\n\n🇲🇽 JIMENEZ!\n\n🎯 Header from the corner! Perfect delivery from Lozano\n\n📊 Mokoena red proves costly!\n\n*Mexico 🇲🇽 2-1 🇿🇦 South Africa*"
        ],
        [
            'minute' => 82,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => '82\' - UNBELIEVABLE!',
            'message' => "*GOAL! SOUTH AFRICA 2-2!* ⚽😱\n\n🇿🇦 TAU AGAIN! BRACE!\n\n💨 Solo run from halfway line! Beats 3 defenders, fires into bottom corner\n\n🔥 10-man South Africa refuse to give up!\n\n📊 Tau: 2 goals, 4 dribbles completed\n\n*Mexico 🇲🇽 2-2 🇿🇦 South Africa*"
        ],
        [
            'minute' => 90,
            'emoji' => '⏱️',
            'type' => 'fulltime',
            'title' => 'Full Time - Extra Time!',
            'message' => "*FULL TIME* ⏱️ 2-2\n\n🤯 What a match! We\'re going to EXTRA TIME!\n\n⚽ Goals:\n• Mexico: Lozano (23\'), Jimenez (75\')\n• South Africa: Tau (38\', 82\')\n\n🔴 Mokoena (South Africa) sent off 67\'\n\n📊 Final 90\' stats:\n• Possession: 54%-46%\n• Shots: 12-9\n• xG: 1.85-1.72\n\n*Extra time: 2x15 minutes*"
        ],
        [
            'minute' => 105,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => "105\' - HAT-TRICK!",
            'message' => "*GOAL! MEXICO 3-2!* ⚽\n\n🇲🇽 LOZANO COMPLETES HAT-TRICK!\n\n🎯 Brilliant solo effort! Dribbles past 2 defenders, curls it into the far corner\n\n📊 Lozano: 3 goals in the World Cup opener!\n📈 First hat-trick of World Cup 2026!\n\n*Mexico 🇲🇽 3-2 🇿🇦 South Africa*"
        ],
        [
            'minute' => 120,
            'emoji' => '⏱️',
            'type' => 'penalties',
            'title' => 'Penalties!',
            'message' => "*FULL TIME ET* ⏱️ 3-3\n\n🇿🇦 Tau hat-trick! (107\' penalty) to force penalties!\n\n🏆 *WORLD CUP OPENER TO BE DECIDED BY PENALTIES*\n\n📊 ET Stats:\n• Shots: 3-2 (Mexico)\n• Saves: 2-1 (Ochoa)\n\n🎯 Penalty shootout begins now...\n\nSend */penalty* to see the result!"
        ],
        [
            'minute' => 121,
            'emoji' => '🏆',
            'type' => 'winner',
            'title' => 'MEXICO WIN!',
            'message' => "*🏆 MEXICO WIN 4-3 ON PENALTIES! 🏆*\n\n🇲🇽 Lozano lifts the World Cup opener trophy!\n\n✅ Scored: Lozano, Herrera, Chavez, Araujo\n❌ Missed: Zwane (saved), Foster (wide)\n\n📊 Final Score: Mexico 3-3 South Africa (4-3 pens)\n\n🎉 What an opening match! Lozano with hat-trick + winning penalty!\n\n*Demo complete! Send SUBSCRIBE for real World Cup 2026 alerts*"
        ]
    ];

    public function __construct(Subscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    public function handle(MessageSender $messageSender)
    {
        Log::info('Starting demo match simulation', ['subscriber' => $this->subscriber->id, 'job_id' => $this->job->uuid ?? 'unknown']);

        // Check if another demo job is already running for this subscriber
        $runningDemos = \DB::table('jobs')
            ->where('queue', 'default')
            ->where('payload', 'like', '%DemoMatchSimulation%')
            ->where('payload', 'like', '%"id":' . $this->subscriber->id . '%')
            ->count();

        if ($runningDemos > 1) {
            Log::info('Duplicate demo job detected, skipping', ['subscriber' => $this->subscriber->id]);
            return;
        }

        foreach (self::MATCH_EVENTS as $index => $event) {
            $this->subscriber->refresh();
            if (!$this->subscriber->demo_mode) {
                Log::info('Demo cancelled - subscriber opted out', ['subscriber' => $this->subscriber->id]);
                return;
            }

            $formattedMessage = "{$event['emoji']} *{$event['title']}*\n\n{$event['message']}";
            
            $result = $messageSender->sendText($this->subscriber->phone_number, $formattedMessage);

            if (!$result) {
                Log::error('Failed to send demo event', [
                    'event' => $event['type'],
                    'error' => 'Message send failed'
                ]);
            }

            Log::info('Demo event sent', [
                'event' => $event['type'],
                'minute' => $event['minute']
            ]);

            if ($index < count(self::MATCH_EVENTS) - 1) {
                sleep(60);
            }
        }

        $this->subscriber->update(['demo_mode' => false]);
        Log::info('Demo match simulation complete', ['subscriber' => $this->subscriber->id]);
    }
}
