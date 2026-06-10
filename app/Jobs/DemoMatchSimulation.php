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
    protected array $events;
    protected int $currentEventIndex = 0;

    /**
     * Match events for demo simulation
     * Each event runs every minute
     */
    protected const MATCH_EVENTS = [
        [
            'minute' => -60,
            'emoji' => '⏰',
            'type' => 'upcoming',
            'title' => 'Match Starting Soon',
            'message' => "Mexico �� vs South Africa �� kicks off in 60 minutes!\n\n📍 Estadio Azteca, Mexico City\n🏆 World Cup 2026 Opening Match\n\nLineups will be available 30 minutes before kickoff."
        ],
        [
            'minute' => -30,
            'emoji' => '📋',
            'type' => 'lineup',
            'title' => 'Lineups Available',
            'message' => "*Starting XI*\n\n�� Mexico:\nOchoa; Araujo, Montes, Vasquez, Gallardo; Alvarez, Herrera, Chavez; Lozano, Jimenez, Antuna\n\n�� South Africa:\nWilliams; Mobbie, Xulu, Mvala, Mashego; Mokoena, Sithole; Zwane, Tau, Mayambela; Foster\n\n🔄 Both teams at full strength!"
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
            'message' => "*The World Cup 2026 is LIVE!* 🏆\n\nMexico �� 0-0 �� South Africa\n\n🎙️ \"The greatest show on earth begins! Mexico hosts the opener at the iconic Estadio Azteca.\"\n\n*1st minute* ⏱️"
        ],
        [
            'minute' => 12,
            'emoji' => '⚡',
            'type' => 'action',
            'title' => '12\' - Close Chance!',
            'message' => 'Nearly!\n\n🇫🇷 Mbappe breaks through on the right, cuts inside... fires just wide of the post!\n\n⚡ Electric pace from the French winger\n📊 xG: 0.12 (Argentina 0.05 - 0.17 France)'
        ],
        [
            'minute' => 23,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => '23\' - GOAL!',
            'message' => '*GOAL! ARGENTINA 1-0!* ⚽🐐\n\n🇲🇽 LOZANO SCORES!\n\n🎯 Magical free-kick curls over the wall and into the top corner!\n\n📊 His 8th World Cup goal - now TIED with Ronaldo for most all-time!\n📈 Argentina dominant in possession (62%)\n\n*Mexico 🇲🇽 1-0 🇿🇦 South Africa*'
        ],
        [
            'minute' => 31,
            'emoji' => '🛑',
            'type' => 'yellow',
            'title' => '31\' - Yellow Card',
            'message' => '🟨 Yellow Card\n\n🇿🇦 Sithole booked for a late challenge on Messi\n\n*France struggling to contain the Argentine midfield*'
        ],
        [
            'minute' => 38,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => '38\' - EQUALIZER!',
            'message' => '*GOAL! FRANCE 1-1!* ⚽\n\n🇿🇦 TAU EQUALIZES!\n\n💨 Lightning counter-attack! Dembele crosses, Mbappe smashes home from 8 yards\n\n🔄 Game on! France back in it\n\n*Mexico 🇲🇽 1-1 🇿🇦 South Africa*'
        ],
        [
            'minute' => 45,
            'emoji' => '⏱️',
            'type' => 'halftime',
            'title' => 'HALF TIME',
            'message' => '*HALF TIME* ⏱️\n\n🇲🇽 Mexico 1-1 France 🇫🇷\n\n⚽ Goals: Messi (23\'), Mbappe (38\')\n\n📊 Stats:\n• Possession: 58%-42%\n• Shots: 8-6\n• xG: 0.85-0.72\n\n🔥 Intense first half! What a final this is!\n\n*Second half in 15 minutes*'
        ],
        [
            'minute' => 60,
            'emoji' => '🔁',
            'type' => 'substitution',
            'title' => '60\' - Substitutions',
            'message' => '🔄 Double Sub\n\n🇲🇽 Mexico: Lautaro Martinez ⬆️ replaces Alvarez ⬇️\n🇿🇦 South Africa: Camavinga ⬆️ replaces Rabiot ⬇️\n\nBoth managers looking for fresh legs in midfield\n\n*Mexico 🇲🇽 1-1 🇿🇦 South Africa*'
        ],
        [
            'minute' => 67,
            'emoji' => '🟥',
            'type' => 'redcard',
            'title' => '67\' - RED CARD!',
            'message' => '*RED CARD!* 🟥\n\n🇿🇦 Mokoena sent off!\n\n🛑 Professional foul on De Paul as last man - clear denial of goal-scoring opportunity\n\n📊 France down to 10 men with 23 minutes + stoppage remaining!\n\n*Mexico 🇲🇽 1-1 🇿🇦 South Africa*'
        ],
        [
            'minute' => 75,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => '75\' - GOAL!',
            'message' => '*GOAL! ARGENTINA 2-1!* ⚽\n\n🇲🇽 JIMENEZ!\n\n🎯 Just 15 minutes after coming on! Messi assist, clinical finish past Maignan\n\n📊 Griezmann red proves costly!\n\n*Mexico 🇲🇽 2-1 🇿🇦 South Africa*'
        ],
        [
            'minute' => 82,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => '82\' - UNBELIEVABLE!',
            'message' => '*GOAL! FRANCE 2-2!* ⚽😱\n\n🇿🇦 TAU AGAIN! BRACE!\n\n💨 Solo run from halfway line! Beats 3 defenders, fires into bottom corner\n\n🔥 10-man France refuse to give up!\n\n📊 Mbappe: 2 goals, 5 dribbles completed\n\n*Mexico 🇲🇽 2-2 🇿🇦 South Africa*'
        ],
        [
            'minute' => 90,
            'emoji' => '⏱️',
            'type' => 'fulltime',
            'title' => 'Full Time - Extra Time!',
            'message' => "*FULL TIME* ⏱️ 2-2\n\n🤯 What a match! We're going to EXTRA TIME!\n\n⚽ Goals:\n• Argentina: Messi (23\'), Lautaro (75\')\n• France: Mbappe (38\', 82\')\n\n🔴 Griezmann (France) sent off 67\'\n\n📊 Final 90\' stats:\n• Possession: 56%-44%\n• Shots: 14-11\n• xG: 1.92-1.67\n\n*Extra time: 2x15 minutes*\""
        ],
        [
            'minute' => 105,
            'emoji' => '⚽',
            'type' => 'goal',
            'title' => "105' - HAT-TRICK!",
            'message' => "*GOAL! ARGENTINA 3-2!* ⚽🐐\n\n🇲🇽 LOZANO COMPLETES HAT-TRICK!\n\n🎯 Rebounds after Maignan saves Lautaro shot, taps into empty net\n\n📊 Messi: 3 goals in a World Cup final!\n📈 First player to score 3+ in final since 1966\n\n*Mexico 🇲🇽 3-2 🇿🇦 South Africa*\""
        ],
        [
            'minute' => 120,
            'emoji' => '⏱️',
            'type' => 'penalties',
            'title' => 'Penalties!',
            'message' => '*FULL TIME ET* ⏱️ 3-3\n\n🇫🇷 Mbappe hat-trick! (107\' penalty) to force penalties!\n\n🏆 *WORLD CUP TO BE DECIDED BY PENALTIES*\n\n📊 ET Stats:\n• Shots: 4-3 (Argentina)\n• Saves: 2-1 (Maignan)\n\n🎯 Penalty shootout begins now...\n\nSend */penalty* to see the result!'
        ],
        [
            'minute' => 121,
            'emoji' => '🏆',
            'type' => 'winner',
            'title' => 'ARGENTINA WIN!',
            'message' => '*🏆 ARGENTINA WIN 4-2 ON PENALTIES! 🏆*\n\n🇦🇷 Messi lifts the World Cup!\n\n✅ Scored: Messi, Dybala, Paredes, Montiel\n❌ Missed: Coman (saved), Tchouameni (wide)\n\n🐐 Messi: 4 goals, 3 assists, World Cup winner\n\n📊 Final Score: Argentina 3-3 France (4-2 pens)\n\n*🎉 This demo has ended! Try the real thing - send SUBSCRIBE*'
        ]
    ];

    public function __construct(Subscriber $subscriber)
    {
        $this->subscriber = $subscriber;
        $this->events = self::MATCH_EVENTS;
    }

    public function handle(MessageSender $messageSender)
    {
        Log::info('Starting demo match simulation', ['subscriber' => $this->subscriber->id]);

        foreach ($this->events as $index => $event) {
            // Check if subscriber is still in demo mode
            $this->subscriber->refresh();
            if (!$this->subscriber->demo_mode) {
                Log::info('Demo cancelled - subscriber opted out', ['subscriber' => $this->subscriber->id]);
                return;
            }

            // Send the event
            $formattedMessage = "{$event['emoji']} *{$event['title']}*\n\n{$event['message']}";
            
            $result = $messageSender->sendText($this->subscriber->phone_number, $formattedMessage);

            if (!$result['success']) {
                Log::error('Failed to send demo event', [
                    'event' => $event['type'],
                    'error' => $result['error'] ?? 'Unknown'
                ]);
            }

            Log::info('Demo event sent', [
                'event' => $event['type'],
                'minute' => $event['minute']
            ]);

            // Wait 1 minute before next event (unless it's the last one)
            if ($index < count($this->events) - 1) {
                sleep(60);
            }
        }

        // Mark demo as complete
        $this->subscriber->update(['demo_mode' => false]);

        Log::info('Demo match simulation complete', ['subscriber' => $this->subscriber->id]);
    }
}
