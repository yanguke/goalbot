<?php

namespace App\Services\WhatsApp;

use App\Models\Subscriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected MessageSender $messageSender;
    protected string $baseUrl;
    protected string $accessToken;
    protected string $phoneNumberId;
    
    public function __construct(MessageSender $messageSender)
    {
        $this->messageSender = $messageSender;
        $this->accessToken = config('services.whatsapp.access_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->baseUrl = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}";
    }
    
    /**
     * Extract message from WhatsApp webhook data
     * Handles: text, button_reply, list_reply
     */
    public function extractMessage(array $details): ?array
    {
        // Button reply
        if (isset($details['messages'][0]['interactive']['button_reply'])) {
            return [
                'type' => 'button',
                'id' => $details['messages'][0]['interactive']['button_reply']['id'] ?? '',
                'title' => $details['messages'][0]['interactive']['button_reply']['title'] ?? '',
                'from' => $details['messages'][0]['from'] ?? ''
            ];
        }
        
        // List reply
        if (isset($details['messages'][0]['interactive']['list_reply'])) {
            return [
                'type' => 'list',
                'id' => $details['messages'][0]['interactive']['list_reply']['id'] ?? '',
                'title' => $details['messages'][0]['interactive']['list_reply']['title'] ?? '',
                'from' => $details['messages'][0]['from'] ?? ''
            ];
        }
        
        // Text message
        if (isset($details['messages'][0]['text']['body'])) {
            return [
                'type' => 'text',
                'body' => strtolower(trim($details['messages'][0]['text']['body'])),
                'from' => $details['messages'][0]['from'] ?? ''
            ];
        }
        
        return null;
    }
    
    /**
     * Verify or create subscriber
     */
    public function verifyUser(string $phoneNumber): Subscriber
    {
        $subscriber = Subscriber::where('phone_number', $phoneNumber)->first();
        
        if (!$subscriber) {
            $subscriber = Subscriber::create([
                'phone_number' => $phoneNumber,
                'is_active' => true,
                'notifications_enabled' => true,
                'demo_mode' => false,
            ]);
            
            Log::info('New subscriber created', ['phone' => $phoneNumber]);
        }
        
        return $subscriber;
    }
    
    /**
     * Send interactive buttons (main menu)
     */
    public function sendMainMenu(string $phoneNumber): bool
    {
        $header = "⚽ Welcome to GoalBot!";
        $body = "AI-powered World Cup 2026 match alerts delivered to your WhatsApp.\n\nWhat would you like to do?";
        $footer = "Choose an option below 👇";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'demo',
                    'title' => '🎬 Demo'
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
        
        $result = $this->messageSender->sendInteractiveButtons(
            $phoneNumber, 
            $header, 
            $body, 
            $footer, 
            $buttons
        );
        
        return $result['success'] ?? false;
    }
    
    /**
     * Send text message
     */
    public function sendText(string $phoneNumber, string $message): bool
    {
        $result = $this->messageSender->sendText($phoneNumber, $message);
        return $result['success'] ?? false;
    }
    
    /**
     * Start demo simulation
     */
    public function startDemo(Subscriber $subscriber): bool
    {
        $subscriber->update(['demo_mode' => true, 'demo_started_at' => now()]);
        
        $this->sendText(
            $subscriber->phone_number,
            "🎬 *Demo Match Starting...*\n\n" .
            "Argentina 🇦🇷 vs France 🇫🇷\n" .
            "World Cup 2026 Final\n\n" .
            "You'll receive match updates every minute. Sit back and enjoy! ⚽"
        );
        
        // Dispatch demo job
        dispatch(new \App\Jobs\DemoMatchSimulation($subscriber));
        
        return true;
    }
    
    /**
     * Handle subscription
     */
    public function subscribeUser(Subscriber $subscriber): bool
    {
        $subscriber->update([
            'is_active' => true, 
            'notifications_enabled' => true,
            'demo_mode' => false
        ]);
        
        return $this->sendText(
            $subscriber->phone_number,
            "✅ *You're subscribed!*\n\n" .
            "You'll receive AI-powered alerts for:\n" .
            "• Goals & match events\n" .
            "• Red cards & penalties\n" .
            "• Match reminders\n" .
            "• Half-time & full-time scores\n\n" .
            "*World Cup 2026 begins June 11, 2026* 🏆"
        );
    }
    
    /**
     * Send pricing information
     */
    public function sendPricing(string $phoneNumber): bool
    {
        return $this->sendText(
            $phoneNumber,
            "💎 *GoalBot Pricing*\n\n" .
            "*Pay Per Match*\n" .
            "• $2.99 per match\n" .
            "• Full AI commentary\n\n" .
            "*Full Tournament* 🏆\n" .
            "• $19.99 one-time\n" .
            "• All 104 matches\n\n" .
            "Reply */pay* for payment instructions."
        );
    }
    
    /**
     * Send welcome message
     */
    public function sendWelcome(string $phoneNumber): bool
    {
        return $this->sendText(
            $phoneNumber,
            "⚽ *GoalBot - World Cup 2026 Alerts*\n\n" .
            "Send *GoalBot* to see options\n" .
            "Send *Demo* to try a simulation\n" .
            "Send *Subscribe* to opt in\n" .
            "Send *Pricing* for rates\n\n" .
            "https://goalbot.devs.mobi"
        );
    }
    
    /**
     * Handle incoming message and route appropriately
     */
    public function handleIncomingMessage(array $messageData): array
    {
        $message = $this->extractMessage($messageData);
        
        if (!$message) {
            return ['status' => 'ignored', 'reason' => 'no_message'];
        }
        
        $phoneNumber = $message['from'];
        $subscriber = $this->verifyUser($phoneNumber);
        
        // Handle by type
        if ($message['type'] === 'button') {
            return $this->handleButtonClick($subscriber, $message['id']);
        }
        
        if ($message['type'] === 'text') {
            return $this->handleTextCommand($subscriber, $message['body']);
        }
        
        return ['status' => 'unhandled_type'];
    }
    
    /**
     * Handle button clicks
     */
    protected function handleButtonClick(Subscriber $subscriber, string $buttonId): array
    {
        switch ($buttonId) {
            case 'demo':
                $this->startDemo($subscriber);
                return ['status' => 'demo_started'];
                
            case 'subscribe':
                $this->subscribeUser($subscriber);
                return ['status' => 'subscribed'];
                
            case 'pricing':
                $this->sendPricing($subscriber->phone_number);
                return ['status' => 'pricing_sent'];
                
            default:
                return ['status' => 'unknown_button'];
        }
    }
    
    /**
     * Handle text commands
     */
    protected function handleTextCommand(Subscriber $subscriber, string $text): array
    {
        // Main keyword triggers menu
        if (str_contains($text, 'goalbot')) {
            $this->sendMainMenu($subscriber->phone_number);
            return ['status' => 'menu_sent'];
        }
        
        // Direct commands
        return match ($text) {
            'demo' => $this->startDemo($subscriber) ? ['status' => 'demo_started'] : ['status' => 'demo_failed'],
            'subscribe', 'opt in', 'join' => $this->subscribeUser($subscriber) ? ['status' => 'subscribed'] : ['status' => 'subscribe_failed'],
            'pricing', 'price' => $this->sendPricing($subscriber->phone_number) ? ['status' => 'pricing_sent'] : ['status' => 'pricing_failed'],
            default => $this->sendWelcome($subscriber->phone_number) ? ['status' => 'welcome_sent'] : ['status' => 'welcome_failed'],
        };
    }
}
