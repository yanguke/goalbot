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
                'notify_all_matches' => true,
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
        // Check if demo already running
        if ($subscriber->demo_mode) {
            $this->sendText(
                $subscriber->phone_number,
                "⏳ Demo already in progress!\n\nCheck your messages - match events are being sent every minute."
            );
            return false;
        }

        $subscriber->update(['demo_mode' => true, 'demo_started_at' => now()]);

        $this->sendText(
            $subscriber->phone_number,
            "🎬 *Demo Match Starting...*\n\n" .
            "Mexico �� vs South Africa ��\n" .
            "World Cup 2026 Opening Match\n\n" .
            "You'll receive match updates every minute. Sit back and enjoy! ⚽"
        );
        
        // Dispatch demo job
        dispatch(new \App\Jobs\DemoMatchSimulation($subscriber));
        
        return true;
    }
    
    /**
     * Handle subscription - show confirmation preview
     */
    public function subscribeUser(Subscriber $subscriber): bool
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $subscriber->phone_number);
        $isKenyan = str_starts_with($cleanNumber, '254');
        
        $header = "⚽ GoalBot Subscription";
        $body = "You're about to subscribe to AI-powered World Cup 2026 alerts:\n\n" .
                "📱 *What you will receive:*\n" .
                "• Goals & match events\n" .
                "• Red cards & penalties\n" .
                "• Match reminders\n" .
                "• Half-time & full-time scores\n\n" .
                ($isKenyan 
                    ? "💰 *Cost:* KES 10 per match or KES 1,000 full tournament" 
                    : "💰 *Cost:* $2.99 per match or $19.99 full tournament");
        $footer = "Tap Continue to proceed 👇";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'confirm_subscribe',
                    'title' => '✅ Continue'
                ]
            ]
        ];
        
        $result = $this->messageSender->sendInteractiveButtons(
            $subscriber->phone_number,
            $header,
            $body,
            $footer,
            $buttons
        );
        
        return $result['success'] ?? false;
    }
    
    /**
     * Confirm subscription and activate
     */
    public function confirmSubscription(Subscriber $subscriber): bool
    {
        $subscriber->update([
            'is_active' => true,
            'notifications_enabled' => true,
            'notify_all_matches' => true,
            'demo_mode' => false
        ]);
        
        // Send confirmation with Pay button
        $cleanNumber = preg_replace('/[^0-9]/', '', $subscriber->phone_number);
        $isKenyan = str_starts_with($cleanNumber, '254');
        
        $header = "✅ Subscription Confirmed!";
        $body = "You're now subscribed to GoalBot!\n\n" .
                "📱 *Your alerts include:*\n" .
                "• Goals & match events\n" .
                "• Red cards & penalties\n" .
                "• Match reminders\n" .
                "• Half-time & full-time scores\n\n" .
                "Complete payment to activate full access.";
        $footer = "World Cup 2026 begins June 11, 2026 🏆";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'pay',
                    'title' => $isKenyan ? '💳 Pay KES 10' : '💳 Pay $2.99'
                ]
            ]
        ];
        
        $result = $this->messageSender->sendInteractiveButtons(
            $subscriber->phone_number,
            $header,
            $body,
            $footer,
            $buttons
        );
        
        return $result['success'] ?? false;
    }
    
    /**
     * Send pricing information
     */
    public function sendPricing(string $phoneNumber): bool
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        if (str_starts_with($cleanNumber, '254')) {
            // Kenyan pricing
            return $this->sendText(
                $phoneNumber,
                "💎 *GoalBot Pricing*\n\n" .
                "*Pay Per Match*\n" .
                "• KES 10 per match\n" .
                "• Full AI commentary\n\n" .
                "*Full Tournament* 🏆\n" .
                "• KES 1,000 one-time\n" .
                "• All 104 matches\n\n" .
                "Reply */pay* for payment instructions."
            );
        }
        
        // International pricing
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
     * Process payment request
     */
    public function processPayment(string $phoneNumber): bool
    {
        // Check if Kenyan number (starts with 254)
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        if (str_starts_with($cleanNumber, '254')) {
            // Kenyan number - initiate M-Pesa STK Push
            return $this->initiateMpesaStkPush($phoneNumber);
        }
        
        // Non-Kenyan number - send secure payment link
        return $this->sendText(
            $phoneNumber,
            "💳 *Secure Payment*\n\n" .
            "Complete your subscription securely:\n" .
            "👉 https://goalbot.devs.mobi/pay?ref=" . urlencode($phoneNumber) . "\n\n" .
            "*Options:*\n" .
            "• $2.99 per match (~€2.75 / ~£2.35)\n" .
            "• $19.99 full tournament (~€18.50 / ~£15.75)\n\n" .
            "Payments processed securely via Stripe."
        );
    }
    
    /**
     * Initiate M-Pesa STK Push for Kenyan numbers
     */
    protected function initiateMpesaStkPush(string $phoneNumber): bool
    {
        $shortcode = config('services.mpesa.shortcode', '174379');
        $passkey = config('services.mpesa.passkey');
        $consumerKey = config('services.mpesa.consumer_key');
        $consumerSecret = config('services.mpesa.consumer_secret');
        
        // Check if MPesa credentials are configured
        if (!$passkey || !$consumerKey || !$consumerSecret) {
            Log::warning('MPesa not configured, sending manual payment instructions', ['phone' => $phoneNumber]);
            return $this->sendText(
                $phoneNumber,
                "💳 *Payment Instructions*\n\n" .
                "Send payment via M-Pesa:\n" .
                "1. Go to M-Pesa menu\n" .
                "2. Select Lipa na M-Pesa\n" .
                "3. Select Buy Goods and Services\n" .
                "4. Enter Till Number: *123456*\n" .
                "5. Amount: KES 10 per match\n" .
                "   or KES 1,000 full tournament\n" .
                "6. Confirm with PIN\n\n" .
                "Reply with screenshot after payment."
            );
        }
        
        // Format phone number for STK (remove 254, add 0)
        $formattedPhone = '0' . substr(preg_replace('/[^0-9]/', '', $phoneNumber), -9);
        
        // Generate timestamp
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);
        
        try {
            // Get access token
            $authResponse = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
            
            if (!$authResponse->successful()) {
                throw new \Exception('Failed to get MPesa access token');
            }
            
            $accessToken = $authResponse->json('access_token');
            
            // Initiate STK Push
            $stkResponse = Http::withToken($accessToken)
                ->post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerBuyGoodsOnline',
                    'Amount' => 10, // KES 10 per match (test amount)
                    'PartyA' => $formattedPhone,
                    'PartyB' => $shortcode,
                    'PhoneNumber' => $formattedPhone,
                    'CallBackURL' => config('app.url') . '/api/mpesa/callback',
                    'AccountReference' => 'GoalBot',
                    'TransactionDesc' => 'World Cup Match Alerts - KES 10'
                ]);
            
            if ($stkResponse->successful()) {
                $this->sendText(
                    $phoneNumber,
                    "📲 *M-Pesa STK Push Initiated*\n\n" .
                    "Check your phone for the M-Pesa prompt.\n" .
                    "Enter your PIN to complete payment.\n\n" .
                    "Amount: KES 10\n" .
                    "Reference: GoalBot\n\n" .
                    "Reply *PAID* after completing payment."
                );
                return true;
            }
            
            Log::error('MPesa STK Push failed', [
                'response' => $stkResponse->json(),
                'phone' => $phoneNumber
            ]);
            
            return $this->sendText(
                $phoneNumber,
                "⚠️ *Payment Request Failed*\n\n" .
                "Please try manual payment:\n" .
                "Till Number: *123456*\n" .
                "Amount: KES 10\n\n" .
                "Reply with screenshot after payment."
            );
            
        } catch (\Exception $e) {
            Log::error('MPesa STK Push exception', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber
            ]);
            
            return $this->sendText(
                $phoneNumber,
                "⚠️ *Payment System Busy*\n\n" .
                "Please use manual M-Pesa:\n" .
                "Till Number: *123456*\n" .
                "Amount: KES 10\n\n" .
                "Reply with screenshot after payment."
            );
        }
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
                return ['status' => 'subscribe_preview_sent'];
                
            case 'confirm_subscribe':
                $this->confirmSubscription($subscriber);
                return ['status' => 'subscribed'];
                
            case 'pricing':
                $this->sendPricing($subscriber->phone_number);
                return ['status' => 'pricing_sent'];
                
            case 'pay':
                $this->processPayment($subscriber->phone_number);
                return ['status' => 'payment_initiated'];
                
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
        if (str_contains($text, 'goalbot') || str_contains($text, 'goal')) {
            $this->sendMainMenu($subscriber->phone_number);
            return ['status' => 'menu_sent'];
        }
        
        // Direct commands
        return match ($text) {
            'demo' => $this->startDemo($subscriber) ? ['status' => 'demo_started'] : ['status' => 'demo_failed'],
            'subscribe', 'opt in', 'join' => $this->subscribeUser($subscriber) ? ['status' => 'subscribed'] : ['status' => 'subscribe_failed'],
            'pricing', 'price' => $this->sendPricing($subscriber->phone_number) ? ['status' => 'pricing_sent'] : ['status' => 'pricing_failed'],
            'pay', '/pay' => $this->processPayment($subscriber->phone_number) ? ['status' => 'payment_initiated'] : ['status' => 'payment_failed'],
            default => $this->sendWelcome($subscriber->phone_number) ? ['status' => 'welcome_sent'] : ['status' => 'welcome_failed'],
        };
    }
}
