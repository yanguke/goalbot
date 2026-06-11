<?php

namespace App\Services\WhatsApp;

use App\Models\MpesaTransaction;
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
     * Handle subscription - show confirmation preview with payment options
     */
    public function subscribeUser(Subscriber $subscriber): bool
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $subscriber->phone_number);
        $isKenyan = str_starts_with($cleanNumber, '254');
        
        if ($isKenyan) {
            // Kenyan user - show KES payment options
            $header = "⚽ GoalBot Subscription";
            $body = "You're about to subscribe to AI-powered World Cup 2026 alerts:\n\n" .
                    "📱 *What you will receive:*\n" .
                    "• Goals & match events\n" .
                    "• Red cards & penalties\n" .
                    "• Match reminders\n" .
                    "• Half-time & full-time scores\n\n" .
                    "💰 *Choose your plan:*";
            $footer = "Tap a button to pay 👇";
            
            $buttons = [
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'pay_per_match',
                        'title' => '49 - Match'
                    ]
                ],
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'pay_full',
                        'title' => '999 - Full'
                    ]
                ]
            ];
        } else {
            // International user - redirect to Stripe
            $header = "⚽ GoalBot Subscription";
            $body = "You're about to subscribe to AI-powered World Cup 2026 alerts:\n\n" .
                    "📱 *What you will receive:*\n" .
                    "• Goals & match events\n" .
                    "• Red cards & penalties\n" .
                    "• Match reminders\n" .
                    "• Half-time & full-time scores\n\n" .
                    "💰 *Cost:* $2.99 per match or $19.99 full tournament";
            $footer = "Tap Continue to proceed 👇";
            
            $buttons = [
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'pay',
                        'title' => '💳 Pay $2.99'
                    ]
                ]
            ];
        }
        
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
                    'title' => $isKenyan ? '49 - Match' : 'Pay $0.99'
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
                "• KES 49 per match\n" .
                "• Full AI commentary\n\n" .
                "*Full Tournament* 🏆\n" .
                "• KES 999 one-time\n" .
                "• All 104 matches\n\n" .
                "Reply */pay* for payment instructions."
            );
        }
        
        // International pricing
        return $this->sendText(
            $phoneNumber,
            "💎 *GoalBot Pricing*\n\n" .
            "*Pay Per Match*\n" .
            "• $0.99 per match\n" .
            "• Full AI commentary\n\n" .
            "*Full Tournament* 🏆\n" .
            "• $9.99 one-time\n" .
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
            "• $0.99 per match (~€0.90 / ~£0.78)\n" .
            "• $9.99 full tournament (~€9.20 / ~£7.85)\n\n" .
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
                "5. Amount: KES 49 per match\n" .
                "   or KES 999 full tournament\n" .
                "6. Confirm with PIN\n\n" .
                "Reply with screenshot after payment."
            );
        }
        
        // Format phone number for STK (254XXXXXXXXX format)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = substr($cleanPhone, 1);
        }
        if (!str_starts_with($cleanPhone, '254')) {
            $cleanPhone = '254' . $cleanPhone;
        }
        $formattedPhone = $cleanPhone;
        
        // Generate timestamp
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);
        
        try {
            // Get access token
            $authResponse = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->get('https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
            
            if (!$authResponse->successful()) {
                throw new \Exception('Failed to get MPesa access token');
            }
            
            $accessToken = $authResponse->json('access_token');
            
            // Initiate STK Push
            $stkResponse = Http::withToken($accessToken)
                ->post('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
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
                "Amount: KES {$amount}\n\n" .
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
                "Amount: KES {$amount}\n\n" .
                "Reply with screenshot after payment."
            );
        }
    }
    
    /**
     * Initiate STK Push with transaction recording
     */
    protected function initiateStkPush(string $phoneNumber, int $amount, string $paymentType): bool
    {
        $shortcode = config('services.mpesa.shortcode', '174379');
        $passkey = config('services.mpesa.passkey');
        $consumerKey = config('services.mpesa.consumer_key');
        $consumerSecret = config('services.mpesa.consumer_secret');
        
        // Check if MPesa credentials are configured
        if (!$passkey || !$consumerKey || !$consumerSecret) {
            Log::warning('MPesa not configured', ['phone' => $phoneNumber]);
            return $this->sendText(
                $phoneNumber,
                "💳 *M-Pesa Not Configured*\n\n" .
                "Please try manual payment:\n" .
                "Till Number: *123456*\n" .
                "Amount: KES {$amount}\n\n" .
                "Reply with screenshot after payment."
            );
        }
        
        // Format phone number for STK (254XXXXXXXXX format, no leading 0)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = substr($cleanPhone, 1);
        }
        if (!str_starts_with($cleanPhone, '254')) {
            $cleanPhone = '254' . $cleanPhone;
        }
        $formattedPhone = $cleanPhone;
        
        // Generate timestamp and password
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);
        
        // Create account reference
        $accountReference = $paymentType === 'full_tournament' ? 'GOALBOT_FULL' : 'GOALBOT_MATCH';
        $transactionDesc = $paymentType === 'full_tournament' 
            ? 'GoalBot Full Tournament - KES 1000' 
            : 'GoalBot Per Match - KES 10';
        
        try {
            // Get access token
            $authResponse = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->get('https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
            
            if (!$authResponse->successful()) {
                throw new \Exception('Failed to get MPesa access token');
            }
            
            $accessToken = $authResponse->json('access_token');
            
            // Initiate STK Push
            $stkResponse = Http::withToken($accessToken)
                ->post('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $formattedPhone,
                    'PartyB' => $shortcode,
                    'PhoneNumber' => $formattedPhone,
                    'CallBackURL' => config('app.url') . '/api/mpesa/callback',
                    'AccountReference' => $accountReference,
                    'TransactionDesc' => $transactionDesc
                ]);
            
            if ($stkResponse->successful()) {
                $responseData = $stkResponse->json();
                
                // Save transaction record
                MpesaTransaction::create([
                    'phone_number' => $phoneNumber,
                    'amount' => $amount,
                    'payment_type' => $paymentType,
                    'checkout_request_id' => $responseData['CheckoutRequestID'] ?? null,
                    'merchant_request_id' => $responseData['MerchantRequestID'] ?? null,
                    'status' => 'pending',
                    'account_reference' => $accountReference
                ]);
                
                $this->sendText(
                    $phoneNumber,
                    "📲 *M-Pesa STK Push Initiated*\n\n" .
                    "Check your phone for the M-Pesa prompt.\n" .
                    "Enter your PIN to complete payment.\n\n" .
                    "Amount: KES {$amount}\n" .
                    "Reference: {$accountReference}\n\n" .
                    "You'll receive confirmation once payment is received."
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
                "Amount: KES {$amount}\n\n" .
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
                "Amount: KES {$amount}\n\n" .
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
                
            case 'pay_per_match':
                $this->initiateStkPush($subscriber->phone_number, 49, 'per_match');
                return ['status' => 'stk_initiated'];
                
            case 'pay_full':
                $this->initiateStkPush($subscriber->phone_number, 999, 'full_tournament');
                return ['status' => 'stk_initiated'];
                
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
        $textLower = strtolower(trim($text));
        return match ($textLower) {
            'demo' => $this->startDemo($subscriber) ? ['status' => 'demo_started'] : ['status' => 'demo_failed'],
            'subscribe', 'opt in', 'join' => $this->subscribeUser($subscriber) ? ['status' => 'subscribed'] : ['status' => 'subscribe_failed'],
            'pricing', 'price' => $this->sendPricing($subscriber->phone_number) ? ['status' => 'pricing_sent'] : ['status' => 'pricing_failed'],
            'pay', '/pay' => $this->processPayment($subscriber->phone_number) ? ['status' => 'payment_initiated'] : ['status' => 'payment_failed'],
            'menu', 'help', 'hi', 'hello', 'start' => $this->sendMainMenu($subscriber->phone_number) ? ['status' => 'menu_sent'] : ['status' => 'menu_failed'],
            default => config('services.anthropic.qa_enabled', true)
                ? $this->handleAIQuestion($subscriber, $text)
                : ($this->sendWelcome($subscriber->phone_number) ? ['status' => 'welcome_sent'] : ['status' => 'welcome_failed']),
        };
    }

    /**
     * Handle free-form questions about World Cup using Claude AI
     */
    protected function handleAIQuestion(Subscriber $subscriber, string $question): array
    {
        $answer = $this->askClaude($question);
        $this->sendText($subscriber->phone_number, $answer);
        return ['status' => 'ai_answered'];
    }

    /**
     * Ask Claude a World Cup question (RAG: includes live fixtures context)
     */
    protected function askClaude(string $question): string
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return "⚽ Hi! Reply *menu* to see options, or *demo* to try it out!";
        }

        // Build RAG context from live fixture data
        $fixturesContext = '';
        try {
            $football = app(\App\Services\Football\FootballDataService::class);
            $fixturesContext = $football->buildFixturesContext();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('RAG context unavailable', ['error' => $e->getMessage()]);
        }

        $systemPrompt = "You are GoalBot, a friendly WhatsApp assistant specialized in FIFA World Cup 2026. " .
            "Answer questions about teams, players, fixtures, history, predictions, and football in general. " .
            "\n\nLIVE TOURNAMENT DATA (use this to answer questions about fixtures, schedules, scores, status):\n" .
            $fixturesContext .
            "\n\nRules:\n" .
            "- ALWAYS prioritize the LIVE TOURNAMENT DATA above when answering schedule, score, or fixture questions\n" .
            "- Convert UTC times to local context when helpful (Kenya is UTC+3, Mexico City is UTC-6)\n" .
            "- Keep replies under 600 characters (WhatsApp friendly)\n" .
            "- Use 1-3 emojis (⚽ 🏆 🔥 🥅)\n" .
            "- Be conversational and energetic\n" .
            "- If the data doesn't have the answer, say so honestly\n" .
            "- For non-football questions, politely redirect to football topics\n" .
            "- End with a follow-up suggestion when appropriate";

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(20)->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-haiku-4-5'),
                'max_tokens' => 400,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $question]
                ],
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                return trim($response->json('content.0.text', "⚽ Hmm, let me think about that. Try asking again!"));
            }

            \Illuminate\Support\Facades\Log::error('Claude Q&A failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Claude Q&A exception', ['error' => $e->getMessage()]);
        }

        return "⚽ Sorry, I'm having trouble right now. Reply *menu* to see options!";
    }
}
