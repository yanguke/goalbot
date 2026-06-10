<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\WhatsApp\MessageSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppInteractiveController extends Controller
{
    protected MessageSender $messageSender;
    
    public function __construct(MessageSender $messageSender)
    {
        $this->messageSender = $messageSender;
    }
    
    /**
     * Handle incoming WhatsApp messages and button clicks
     */
    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info('WhatsApp webhook received', $data);
        
        // Extract message details
        $entry = $data['entry'] ?? [];
        $changes = $entry[0]['changes'] ?? [];
        $value = $changes[0]['value'] ?? [];
        
        // Handle button replies
        if (isset($value['messages'][0]['interactive']['button_reply'])) {
            return $this->handleButtonReply($value['messages'][0]);
        }
        
        // Handle text messages
        if (isset($value['messages'][0]['text']['body'])) {
            return $this->handleTextMessage($value['messages'][0]);
        }
        
        return response()->json(['status' => 'ignored']);
    }
    
    /**
     * Handle text messages - look for "GoalBot" keyword
     */
    protected function handleTextMessage(array $message)
    {
        $phoneNumber = $message['from'] ?? '';
        $text = strtolower(trim($message['text']['body'] ?? ''));
        
        // Register or get subscriber
        $subscriber = Subscriber::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['is_active' => true]
        );
        
        // Check for GoalBot keyword
        if (str_contains($text, 'goalbot')) {
            return $this->sendMainMenu($phoneNumber, $subscriber);
        }
        
        // Handle demo, subscribe, pricing keywords directly
        if ($text === 'demo') {
            return $this->startDemo($phoneNumber, $subscriber);
        }
        
        if ($text === 'subscribe' || $text === 'opt in' || $text === 'join') {
            return $this->handleSubscribe($phoneNumber, $subscriber);
        }
        
        if ($text === 'pricing' || $text === 'price') {
            return $this->sendPricing($phoneNumber);
        }
        
        // Default: send welcome with instructions
        return $this->sendWelcomeMessage($phoneNumber);
    }
    
    /**
     * Send main menu with 3 buttons
     */
    protected function sendMainMenu(string $phoneNumber, Subscriber $subscriber)
    {
        $header = "⚽ Welcome to GoalBot!";
        $body = "AI-powered World Cup 2026 match alerts delivered to your WhatsApp.\n\nWhat would you like to do?";
        $footer = "Choose an option below 👇";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'demo',
                    'title' => '▶️ Demo'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'subscribe',
                    'title' => '✅ Subscribe'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'pricing',
                    'title' => '💎 Pricing'
                ]
            ]
        ];
        
        $this->messageSender->sendInteractiveButtons($phoneNumber, $header, $body, $footer, $buttons);
        
        return response()->json(['status' => 'menu_sent']);
    }
    
    /**
     * Handle button reply clicks
     */
    protected function handleButtonReply(array $message)
    {
        $phoneNumber = $message['from'] ?? '';
        $buttonId = $message['interactive']['button_reply']['id'] ?? '';
        
        $subscriber = Subscriber::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['is_active' => true]
        );
        
        return match ($buttonId) {
            'demo' => $this->startDemo($phoneNumber, $subscriber),
            'subscribe' => $this->handleSubscribe($phoneNumber, $subscriber),
            'pricing' => $this->sendPricing($phoneNumber),
            default => response()->json(['status' => 'unknown_button'])
        };
    }
    
    /**
     * Start demo match simulation
     */
    protected function startDemo(string $phoneNumber, Subscriber $subscriber)
    {
        // Mark subscriber as in demo mode
        $subscriber->update(['demo_mode' => true, 'demo_started_at' => now()]);
        
        // Send welcome message
        $this->messageSender->sendText(
            $phoneNumber,
            "🎬 *Demo Match Starting...*\n\n" .
            "Argentina 🇦🇷 vs France 🇫🇷\n" .
            "World Cup 2026 Final\n\n" .
            "I'll send you match updates every minute. Sit back and enjoy the simulation! ⚽"
        );
        
        // Dispatch demo simulation job
        dispatch(new \App\Jobs\DemoMatchSimulation($subscriber));
        
        return response()->json(['status' => 'demo_started']);
    }
    
    /**
     * Handle subscription
     */
    protected function handleSubscribe(string $phoneNumber, Subscriber $subscriber)
    {
        $subscriber->update(['is_active' => true, 'demo_mode' => false]);
        
        $this->messageSender->sendText(
            $phoneNumber,
            "✅ *You're subscribed!*\n\n" .
            "You'll receive AI-powered alerts for:\n" .
            "• Goals & match events\n" .
            "• Red cards & penalties\n" .
            "• Match kickoff reminders\n" .
            "• Half-time & full-time scores\n\n" .
            "Set your favorite team to get personalized alerts.\n" .
            "Reply with: */favorite [team name]*\n\n" .
            "*World Cup 2026 begins June 11, 2026* 🏆"
        );
        
        return response()->json(['status' => 'subscribed']);
    }
    
    /**
     * Send pricing information
     */
    protected function sendPricing(string $phoneNumber)
    {
        $this->messageSender->sendText(
            $phoneNumber,
            "💎 *GoalBot Pricing*\n\n" .
            "*Pay Per Match*\n" .
            "• $2.99 per match\n" .
            "• Full AI commentary\n" .
            "• All key events\n\n" .
            "*Full Tournament* 🏆\n" .
            "• $19.99 one-time\n" .
            "• All 104 matches\n" .
            "• Save 35%\n\n" .
            "*How to pay:*\n" .
            "Reply with */pay* and we'll send payment instructions.\n\n" .
            "*Money-back guarantee* if you're not satisfied!"
        );
        
        return response()->json(['status' => 'pricing_sent']);
    }
    
    /**
     * Send welcome message for unrecognized text
     */
    protected function sendWelcomeMessage(string $phoneNumber)
    {
        $this->messageSender->sendText(
            $phoneNumber,
            "⚽ *GoalBot - World Cup 2026 Alerts*\n\n" .
            "Get AI-powered match notifications on WhatsApp.\n\n" .
            "*Try it now:*\n" .
            "Send *GoalBot* to see options\n" .
            "Send *Demo* to try a simulation\n" .
            "Send *Subscribe* to opt in\n" .
            "Send *Pricing* for rates\n\n" .
            "Visit: https://goalbot.devs.mobi"
        );
        
        return response()->json(['status' => 'welcome_sent']);
    }
}
