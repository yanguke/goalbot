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
                'subscription_type' => 'full_tournament',
                'commentary_mode' => 'digest',
            ]);

            Log::info('New subscriber created (full tournament)', ['phone' => $phoneNumber]);

            // Send smooth welcome message
            $this->sendSmoothWelcome($phoneNumber);
        }
        
        return $subscriber;
    }
    
    /**
     * Send interactive buttons (main menu)
     */
    public function sendMainMenu(string $phoneNumber): bool
    {
        // Build a live score hook from today's matches
        $liveHook = $this->buildLiveScoreHook();

        $header = "⚽ FIFA World Cup 2026";
        $body = $liveHook . "\n\nAsk me anything — scores, lineups, groups, predictions. Or tap below 👇";
        $footer = "You can also just type any question!";

        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'schedule',
                    'title' => '📅 Today\'s Matches'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'table',
                    'title' => '🏆 Group Standings'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'favorite',
                    'title' => '⭐ My Team'
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
     * Build a short live score hook for the main menu opening message
     */
    protected function buildLiveScoreHook(): string
    {
        try {
            $football = app(\App\Services\Football\FootballDataService::class);
            $live = $football->getLiveMatches();
            $today = $football->getMatchesForDate(now()->toDateString());

            // Prefer live matches for maximum urgency
            if (!empty($live)) {
                $m = $live[0];
                $home = $m['teams']['home']['name'];
                $away = $m['teams']['away']['name'];
                $hg   = $m['goals']['home'] ?? 0;
                $ag   = $m['goals']['away'] ?? 0;
                $min  = $m['fixture']['status']['elapsed'] ?? '?';
                return "🔴 *LIVE NOW:* {$home} {$hg}–{$ag} {$away} ({$min}')";
            }

            // Show today's results or upcoming
            $played = collect($today)->filter(fn($m) => in_array($m['fixture']['status']['short'], ['FT','AET','PEN']))->values();
            $upcoming = collect($today)->filter(fn($m) => $m['fixture']['status']['short'] === 'NS')->values();

            $lines = [];
            foreach ($played->take(2) as $m) {
                $home = $m['teams']['home']['name'];
                $away = $m['teams']['away']['name'];
                $hg   = $m['goals']['home'];
                $ag   = $m['goals']['away'];
                $lines[] = "✅ {$home} {$hg}–{$ag} {$away}";
            }
            foreach ($upcoming->take(2) as $m) {
                $home = $m['teams']['home']['name'];
                $away = $m['teams']['away']['name'];
                $time = \Carbon\Carbon::parse($m['fixture']['date'])->timezone('Africa/Nairobi')->format('H:i');
                $lines[] = "⏰ {$home} vs {$away} at {$time}";
            }

            if (!empty($lines)) {
                return implode("\n", $lines);
            }
        } catch (\Exception $e) {
            // Silently fall through
        }

        return "The World Cup is here! 48 teams, 104 matches, one trophy. 🏆";
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
                    "📱 *What you get:*\n" .
                    "• Instant goal & event alerts\n" .
                    "• Red cards & penalties\n" .
                    "• AI match commentary\n" .
                    "• Half-time & full-time scores\n\n" .
                    "💰 *Choose your plan:*\n" .
                    "• KES 49 — full day access (today)\n" .
                    "• KES 1,999 — full tournament (all 104 matches)";
            $footer = "Tap a button to pay via M-Pesa 👇";
            
            $buttons = [
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'pay_per_match',
                        'title' => 'KES 49 - Today'
                    ]
                ],
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'pay_full',
                        'title' => 'KES 1999 - Full'
                    ]
                ]
            ];
        } else {
            // International user - redirect to Stripe
            $header = "⚽ GoalBot Subscription";
            $body = "You're about to subscribe to AI-powered World Cup 2026 alerts:\n\n" .
                    "📱 *What you get:*\n" .
                    "• Instant goal & event alerts\n" .
                    "• Red cards & penalties\n" .
                    "• AI match commentary\n" .
                    "• Half-time & full-time scores\n\n" .
                    "💰 *Plans:*\n" .
                    "• \$0.99 — full day access\n" .
                    "• \$9.99 — full tournament";
            $footer = "Tap to choose 👇";
            
            $buttons = [
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'pay_per_match',
                        'title' => '$0.99 - Today'
                    ]
                ],
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'pay_full',
                        'title' => '$9.99 - Full'
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
        $body = "Great choice! Complete payment to activate your alerts.\n\n" .
                "📱 *You'll receive:*\n" .
                "• Instant goal & event alerts\n" .
                "• Red cards, penalties & VAR\n" .
                "• AI match commentary\n" .
                "• Half-time & full-time scores";
        $footer = $isKenyan ? "KES 49/day or KES 1,999 full tournament" : "\$0.99/day or \$9.99 full tournament";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'pay_per_match',
                    'title' => $isKenyan ? 'KES 49 - Today' : '$0.99 - Today'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'pay_full',
                    'title' => $isKenyan ? 'KES 1999 - Full' : '$9.99 - Full'
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
            return $this->sendText(
                $phoneNumber,
                "💎 *GoalBot Pricing*\n\n" .
                "*Day Pass* — KES 49\n" .
                "• Full access for today\n" .
                "• All matches, all alerts\n" .
                "• AI commentary & predictions\n\n" .
                "*Full Tournament* — KES 1,999\n" .
                "• All 104 World Cup matches\n" .
                "• Never miss a goal all summer\n" .
                "• Best value — just KES 20/day\n\n" .
                "Reply *subscribe* to pay via M-Pesa."
            );
        }

        return $this->sendText(
            $phoneNumber,
            "💎 *GoalBot Pricing*\n\n" .
            "*Day Pass* — \$0.99\n" .
            "• Full access for today\n" .
            "• All matches, all alerts\n" .
            "• AI commentary & predictions\n\n" .
            "*Full Tournament* — \$9.99\n" .
            "• All 104 World Cup matches\n" .
            "• Best value\n\n" .
            "Reply *subscribe* to get started."
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
     * Send smooth welcome message
     */
    public function sendSmoothWelcome(string $phoneNumber): bool
    {
        $welcome = "⚽ *Welcome to GoalBot!*\n\n";
        $welcome .= "Hi! I'm GoalBot, your personal AI assistant for the World Cup, here to guide you through the entire tournament. 🤖\n\n";
        $welcome .= "I'll keep you up to date and manage all the important information so you never miss a moment:\n";
        $welcome .= "⚡ *Instant goal alerts* as they happen\n";
        $welcome .= "🎯 *Key match moments* you don't want to miss\n";
        $welcome .= "📱 *Smart updates* only when it matters\n";
        $welcome .= "🏆 *Complete tournament coverage* from start to finish\n\n";
        $welcome .= "Think of me as your personal football expert - always here when you need me!\n\n";
        $welcome .= "*Let's get you set up - choose your experience:*\n";
        $welcome .= "1️⃣ My favorite team\n";
        $welcome .= "2️⃣ Alert preferences\n";
        $welcome .= "3️⃣ Today's matches\n";
        $welcome .= "4️⃣ More options\n\n";
        $welcome .= "Ready to begin? Just pick a number 👇";
        
        return $this->sendText($phoneNumber, $welcome);
    }
    
    /**
     * Send welcome message (legacy)
     */
    public function sendWelcome(string $phoneNumber): bool
    {
        return $this->sendSmoothWelcome($phoneNumber);
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
        if ($message['type'] === 'button' || $message['type'] === 'list') {
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
            case 'favorite':
                $this->sendFavoriteTeamPrompt($subscriber->phone_number);
                return ['status' => 'favorite_prompt_sent'];
                
            case 'commentary':
                $this->sendCommentaryStylePrompt($subscriber->phone_number, $subscriber);
                return ['status' => 'commentary_prompt_sent'];
                
            case 'schedule':
                $this->sendTodayResults($subscriber->phone_number);
                return ['status' => 'schedule_sent'];
                
            case 'table':
                $this->sendStandings($subscriber->phone_number);
                return ['status' => 'table_sent'];
                
            case 'lineups':
                $this->sendLineups($subscriber->phone_number);
                return ['status' => 'lineups_sent'];
                
            case 'stats':
                $this->sendLiveStats($subscriber->phone_number);
                return ['status' => 'stats_sent'];
                
            case 'upgrade_premium':
                $this->sendPremiumInfo($subscriber->phone_number);
                return ['status' => 'premium_info_sent'];
                
            case 'keep_free':
                $this->sendText($subscriber->phone_number, "📊 Great! You'll continue getting all the essential World Cup updates for free.\n\nType *menu* anytime to explore more features!");
                return ['status' => 'continue_free'];
                
            case 'learn_more':
                $this->sendPremiumInfo($subscriber->phone_number);
                return ['status' => 'premium_info_sent'];
                
            case 'demo':
                $this->sendText($subscriber->phone_number, "🎬 Demo mode removed! You now have full tournament access.\n\nType *menu* to see your options.");
                return ['status' => 'demo_removed'];
                
            case 'subscribe':
            case 'subscribe_alerts':
                $this->subscribeUser($subscriber);
                return ['status' => 'subscribe_shown'];
                
            case 'pay_per_match':
                $this->sendText($subscriber->phone_number, "✅ You already have full tournament access!\n\nNo payment needed. Type *menu* to see options.");
                return ['status' => 'already_paid'];
                
            case 'pay_full':
                $this->sendText($subscriber->phone_number, "✅ You already have full tournament access!\n\nNo payment needed. Type *menu* to see options.");
                return ['status' => 'already_paid'];
                
            case 'pricing':
                $this->sendText($subscriber->phone_number, "🎉 Good news! You have full tournament access at no cost.\n\nType *menu* to customize your experience.");
                return ['status' => 'free_access'];
                
            case 'pay':
                $this->sendText($subscriber->phone_number, "✅ You already have full tournament access!\n\nNo payment needed. Type *menu* to see options.");
                return ['status' => 'already_paid'];
                
            default:
                // Handle match-specific buttons (match_12345)
                if (str_starts_with($buttonId, 'match_')) {
                    $fixtureId = substr($buttonId, 6);
                    $this->sendMatchDetails($subscriber->phone_number, $fixtureId);
                    return ['status' => 'match_details_sent'];
                }
                
                // Handle match detail buttons (lineups_12345, stats_12345, etc.)
                if (str_contains($buttonId, '_')) {
                    $parts = explode('_', $buttonId);
                    if (count($parts) === 2) {
                        $action = $parts[0];
                        $fixtureId = $parts[1];
                        
                        switch ($action) {
                            case 'lineups':
                                $this->sendLineups($subscriber->phone_number, $fixtureId);
                                return ['status' => 'lineups_sent'];
                            case 'stats':
                                $this->sendLiveStats($subscriber->phone_number, $fixtureId);
                                return ['status' => 'stats_sent'];
                            case 'alerts':
                                $this->sendMatchAlertPrompt($subscriber->phone_number, $fixtureId);
                                return ['status' => 'alert_prompt_sent'];
                            case 'commentary':
                                $this->sendMatchCommentary($subscriber->phone_number, $fixtureId);
                                return ['status' => 'commentary_sent'];
                        }
                    }
                }
                return ['status' => 'unknown_button'];
        }
    }
    
    
    protected function sendPaywall(Subscriber $subscriber): array
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $subscriber->phone_number);
        $isKenyan = str_starts_with($cleanNumber, '254');

        $msg = "🔒 *Premium Feature*\n\n";
        $msg .= "Live alerts & AI Q&A require a GoalBot subscription.\n\n";
        $msg .= $isKenyan
            ? "💳 *KES 49* for full day access, or *KES 1,999* for the entire tournament.\n\n"
            : "💳 *\$0.99* for full day access, or *\$9.99* for the entire tournament.\n\n";
        $msg .= "Reply *subscribe* to pay, or *demo* to try a free preview.";

        $this->sendText($subscriber->phone_number, $msg);
        return ['status' => 'paywall_sent'];
    }

    protected function sendLineups(string $phone, ?string $fixtureId = null): bool
    {
        $football = app(\App\Services\Football\FootballDataService::class);
        $fixtureId = $fixtureId ?? (string) $football->getTodayFixtureId();

        if (!$fixtureId) {
            return $this->sendText($phone, "⚽ No live or today's match found. Check back closer to kickoff!");
        }

        $lineups = $football->getLineups($fixtureId);

        if (empty($lineups)) {
            return $this->sendText($phone, "📋 Lineups not announced yet. They usually drop 1 hour before kickoff — check back soon!");
        }

        $msg = "📋 *Starting Lineups*\n\n";
        foreach ($lineups as $team) {
            $name = $team['team']['name'];
            $formation = $team['formation'] ?? 'N/A';
            $msg .= "*{$name}* ({$formation})\n";
            $starters = collect($team['startXI'] ?? [])->map(fn($p) => $p['player']['number'] . '. ' . $p['player']['name'])->implode("\n");
            $msg .= $starters . "\n\n";
        }

        return $this->sendText($phone, trim($msg));
    }

    protected function sendLiveStats(string $phone, ?string $fixtureId = null): bool
    {
        $football = app(\App\Services\Football\FootballDataService::class);

        if ($fixtureId) {
            return $this->sendStatsForFixture($phone, (int) $fixtureId, $football);
        }

        $live = $football->getLiveMatches();

        if (empty($live)) {
            return $this->sendText($phone, "📊 No live match right now. Stats are available during matches!");
        }

        $match = $live[0];
        $fixtureId = $match['fixture']['id'];
        $home = $match['teams']['home']['name'];
        $away = $match['teams']['away']['name'];
        $score = ($match['goals']['home'] ?? 0) . '-' . ($match['goals']['away'] ?? 0);
        $minute = $match['fixture']['status']['elapsed'] ?? '?';

        $stats = $football->getLiveStats($fixtureId);

        if (empty($stats)) {
            return $this->sendText($phone, "📊 Stats not available yet for {$home} vs {$away}. Try again in a few minutes!");
        }

        $statMap = [];
        foreach ($stats as $teamStats) {
            $tName = $teamStats['team']['name'];
            foreach ($teamStats['statistics'] ?? [] as $s) {
                $statMap[$s['type']][$tName] = $s['value'] ?? '-';
            }
        }

        $msg = "📊 *Live Stats — {$minute}'*\n";
        $msg .= "{$home} {$score} {$away}\n\n";

        $show = ['Ball Possession', 'Total Shots', 'Shots on Goal', 'Corner Kicks', 'Fouls', 'Yellow Cards'];
        foreach ($show as $stat) {
            if (isset($statMap[$stat])) {
                $h = $statMap[$stat][$home] ?? '-';
                $a = $statMap[$stat][$away] ?? '-';
                $msg .= "{$h} | {$stat} | {$a}\n";
            }
        }

        return $this->sendText($phone, trim($msg));
    }

    protected function sendStatsForFixture(string $phone, int $fixtureId, $football): bool
    {
        $stats = $football->getLiveStats($fixtureId);

        if (empty($stats)) {
            return $this->sendText($phone, "📊 Stats not available yet for this match. Try again once it kicks off!");
        }

        $statMap = [];
        $teams   = [];
        foreach ($stats as $teamStats) {
            $tName   = $teamStats['team']['name'];
            $teams[] = $tName;
            foreach ($teamStats['statistics'] ?? [] as $s) {
                $statMap[$s['type']][$tName] = $s['value'] ?? '-';
            }
        }

        $home = $teams[0] ?? '?';
        $away = $teams[1] ?? '?';
        $msg  = "📊 *Stats — {$home} vs {$away}*\n\n";

        foreach (['Ball Possession', 'Total Shots', 'Shots on Goal', 'Corner Kicks', 'Fouls', 'Yellow Cards'] as $stat) {
            if (isset($statMap[$stat])) {
                $h = $statMap[$stat][$home] ?? '-';
                $a = $statMap[$stat][$away] ?? '-';
                $msg .= "{$h} | {$stat} | {$a}\n";
            }
        }

        return $this->sendText($phone, trim($msg));
    }

    /**
     * Send favorite team prompt
     */
    protected function sendFavoriteTeamPrompt(string $phone): bool
    {
        $message = "🌟 *Choose Your Favorite Team*\n\n";
        $message .= "Type your team name (e.g., Brazil, England, France)\n\n";
        $message .= "Popular teams:\n";
        $message .= "🇧🇷 Brazil • 🇦🇷 Argentina • 🇫🇷 France\n";
        $message .= "🇪🇸 Spain • 🇩🇪 Germany • 🏴󠁧󠁢󠁥󠁮󠁧󠁿 England\n";
        $message .= "🇵🇹 Portugal • 🇳🇱 Netherlands • 🇧🇪 Belgium\n\n";
        $message .= "Reply with your team name or *menu* to go back";
        
        return $this->sendText($phone, $message);
    }
    
    /**
     * Send commentary style prompt
     */
    protected function sendCommentaryStylePrompt(string $phone, Subscriber $subscriber): bool
    {
        $current = $subscriber->commentary_mode ?? 'digest';
        $status = $subscriber->notifications_enabled ? 'ON' : 'OFF';
        
        $message = "⚙️ *Your Alert Preferences*\n\n";
        $message .= "� *Status:* {$status}\n";
        $message .= "� *Style:* " . ucfirst($current) . "\n\n";
        $message .= "*Choose your experience:*\n";
        $message .= "🎯 *Smart alerts* - Big moments only (recommended)\n";
        $message .= "⚡ *Live updates* - Every key play instantly\n\n";
        $message .= "Perfect for staying ahead! 🏆\n\n";
        $message .= "Reply: *smart* or *live*\n";
        $message .= "Or: *pause* / *resume*\n";
        $message .= "Type *menu* to go back";
        
        return $this->sendText($phone, $message);
    }
    
    /**
     * Send premium upgrade prompt (subtle introduction)
     */
    protected function sendPremiumUpgradePrompt(string $phone): bool
    {
        $header = "⭐ Unlock Premium Features";
        $body = "You're getting the most out of GoalBot! Ready to enhance your World Cup experience with exclusive features?";
        $footer = "Join fans who never miss a moment! 🏆";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'upgrade_premium',
                    'title' => '🚀 Upgrade to Premium'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'keep_free',
                    'title' => '📊 Continue Free'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'learn_more',
                    'title' => '❓ Learn More'
                ]
            ]
        ];
        
        $result = $this->messageSender->sendInteractiveButtons(
            $phone, 
            $header, 
            $body, 
            $footer, 
            $buttons
        );
        
        return $result['success'] ?? false;
    }
    
    /**
     * Send premium information
     */
    protected function sendPremiumInfo(string $phone): bool
    {
        $header = "🚀 GoalBot Premium";
        $body = "Unlock the complete World Cup experience with exclusive features:\n\n⭐ Advanced match analytics\n🎯 Custom player alerts\n📊 Detailed statistics\n🏆 Tournament insights\n\nPerfect for the ultimate football fan!";
        $footer = "Choose your plan 👇";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'premium_monthly',
                    'title' => '💎 Monthly Premium'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'premium_tournament',
                    'title' => '🏆 Tournament Pass'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'menu',
                    'title' => '🔙 Back to Menu'
                ]
            ]
        ];
        
        $result = $this->messageSender->sendInteractiveButtons(
            $phone, 
            $header, 
            $body, 
            $footer, 
            $buttons
        );
        
        return $result['success'] ?? false;
    }
    
    /**
     * Send detailed match information with interactive options
     */
    protected function sendMatchDetails(string $phone, string $fixtureId): bool
    {
        $football = app(\App\Services\Football\FootballDataService::class);

        // Search today, yesterday, 2 days ago, and upcoming
        $match = null;
        $pools = [
            $football->getTodayResults(),
            $football->getMatchesForDate(now()->subDay()->toDateString()),
            $football->getMatchesForDate(now()->subDays(2)->toDateString()),
            $football->getUpcomingMatches(5),
        ];
        foreach ($pools as $pool) {
            foreach ($pool as $m) {
                if ((string)$m['fixture']['id'] === $fixtureId) {
                    $match = $m;
                    break 2;
                }
            }
        }

        if (!$match) {
            return $this->sendText($phone, "❌ Match details not found. Please try again.");
        }

        $home    = $match['teams']['home']['name'];
        $away    = $match['teams']['away']['name'];
        $hg      = $match['goals']['home'] ?? '-';
        $ag      = $match['goals']['away'] ?? '-';
        $status  = $match['fixture']['status']['short'] ?? 'NS';
        $elapsed = $match['fixture']['status']['elapsed'] ?? 0;
        $fId     = (int) $fixtureId;

        // ── PRE-MATCH (NS) ──────────────────────────────────────────────
        if ($status === 'NS') {
            $kickoff = \Carbon\Carbon::parse($match['fixture']['date'])
                ->timezone('Africa/Nairobi')->format('D d M, H:i') . ' EAT';

            $bodyLines = ["⏰ *Kickoff:* {$kickoff}", ""];

            // Predictions
            $pred = $football->getPredictions($fId);
            if ($pred) {
                $pct    = $pred['predictions']['percent'] ?? [];
                $advice = $pred['predictions']['advice'] ?? null;
                $winner = $pred['predictions']['winner']['name'] ?? null;
                if ($winner)  $bodyLines[] = "🎯 *Predicted winner:* {$winner}";
                if ($advice)  $bodyLines[] = "💡 *Tip:* {$advice}";
                if ($pct)     $bodyLines[] = "📊 *Win%:* {$home} " . ($pct['home'] ?? '?') . " | Draw " . ($pct['draw'] ?? '?') . " | {$away} " . ($pct['away'] ?? '?');
            }

            // Odds
            $odds = $football->getOdds($fId);
            if (!empty($odds['bets'])) {
                $mw = $odds['bets']['Match Winner'] ?? $odds['bets'][array_key_first($odds['bets'])] ?? [];
                if ($mw) {
                    $parts = [];
                    foreach ($mw as $label => $odd) $parts[] = "{$label} {$odd}";
                    $bodyLines[] = "💰 *Odds:* " . implode(' | ', $parts);
                }
            }

            // Injuries
            $injuries = $football->getInjuries($fId);
            if (!empty($injuries)) {
                $names = collect($injuries)->take(3)->map(fn($i) => $i['player']['name'] . ' (' . $i['team']['name'] . ')')->implode(', ');
                $bodyLines[] = "🏥 *Injuries:* {$names}";
            }

            $sections = [
                ['title' => '🔍 Match Info', 'rows' => [
                    ['id' => "lineups_{$fixtureId}",  'title' => '👥 Starting Lineups',   'description' => 'See who is in the starting XI'],
                    ['id' => "subscribe_alerts",       'title' => '🔔 Get Goal Alerts',    'description' => 'Instant alert when a goal goes in'],
                    ['id' => "commentary_{$fixtureId}",'title' => '💬 Live Commentary',    'description' => 'Minute-by-minute match commentary'],
                ]],
                ['title' => '📊 More', 'rows' => [
                    ['id' => 'table',    'title' => '🏆 Group Standings', 'description' => 'See the full group table'],
                    ['id' => 'schedule', 'title' => '� All Today\'s Matches', 'description' => 'View the full matchday'],
                ]],
            ];

            return ($this->messageSender->sendListMessage(
                $phone,
                "⚽ {$home} vs {$away}",
                implode("\n", $bodyLines),
                'Tap below to explore ↓',
                '🔍 Explore Match',
                $sections
            )['success'] ?? false);
        }

        // ── LIVE / POST-MATCH ────────────────────────────────────────────
        $statusLabel = match ($status) {
            '1H', '2H' => "🔴 LIVE {$elapsed}'",
            'HT'        => '⏸ Half Time',
            'FT'        => '✅ Full Time',
            'AET'       => '✅ AET',
            'PEN'       => '✅ Penalties',
            default     => $status,
        };

        $sections = [
            ['title' => '⚽ This Match', 'rows' => [
                ['id' => "lineups_{$fixtureId}",    'title' => '👥 Lineups',          'description' => 'Starting XIs and formations'],
                ['id' => "stats_{$fixtureId}",      'title' => '📊 Live Stats',       'description' => 'Possession, shots, corners'],
                ['id' => "commentary_{$fixtureId}", 'title' => '💬 Commentary',       'description' => 'Minute-by-minute action'],
            ]],
            ['title' => '🔔 Alerts', 'rows' => [
                ['id' => 'subscribe_alerts', 'title' => '⚽ Get Goal Alerts', 'description' => 'Instant alerts for every goal'],
                ['id' => 'schedule',         'title' => '📅 All Today\'s Matches', 'description' => 'See the full matchday'],
            ]],
        ];

        return ($this->messageSender->sendListMessage(
            $phone,
            "⚽ {$home} {$hg}–{$ag} {$away}",
            "{$statusLabel}\n\nSelect an option below to dive deeper:",
            'Tap any option 👇',
            '🔍 Explore Match',
            $sections
        )['success'] ?? false);
    }
    
    /**
     * Send match alert prompt
     */
    protected function sendMatchAlertPrompt(string $phone, string $fixtureId): bool
    {
        $header = "🔔 Match Alerts";
        $body = "Get instant notifications for this specific match! Choose what you'd like to receive:";
        $footer = "Never miss a moment! ⚡";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'alerts_goals_' . $fixtureId,
                    'title' => '⚽ Goals Only'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'alerts_all_' . $fixtureId,
                    'title' => '🎯 All Key Moments'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'match_' . $fixtureId,
                    'title' => '🔙 Back to Match'
                ]
            ]
        ];
        
        $result = $this->messageSender->sendInteractiveButtons(
            $phone, 
            $header, 
            $body, 
            $footer, 
            $buttons
        );
        
        return $result['success'] ?? false;
    }
    
    /**
     * Send match commentary
     */
    protected function sendMatchCommentary(string $phone, string $fixtureId): bool
    {
        $football = app(\App\Services\Football\FootballDataService::class);
        
        // Get match for commentary
        $matches = $football->getTodayResults();
        $match = null;
        
        foreach ($matches as $m) {
            if ((string)$m['fixture']['id'] === $fixtureId) {
                $match = $m;
                break;
            }
        }
        
        if (!$match) {
            return $this->sendText($phone, "❌ Commentary not available for this match.");
        }
        
        $home = $match['teams']['home']['name'];
        $away = $match['teams']['away']['name'];
        
        // Get LiveScore slug for commentary
        $lsSlug = $football->getLiveScoreFullSlug($home, $away, $fixtureId);
        
        if (!$lsSlug) {
            return $this->sendText($phone, "❌ Live commentary not available for this match yet.");
        }
        
        $commentary = app(\App\Services\Football\LiveScoreCommentaryService::class);
        $entries = $commentary->getCommentary($lsSlug);
        
        if (empty($entries)) {
            return $this->sendText($phone, "💬 *Live Commentary*\n\n{$home} vs {$away}\n\nNo commentary available yet. Match may not have started or commentary is not enabled for this match.");
        }
        
        $msg = "💬 *Live Commentary*\n\n{$home} vs {$away}\n\n";
        foreach (array_slice($entries, 0, 5) as $entry) {
            $msg .= "📝 {$entry['time']} - {$entry['text']}\n\n";
        }
        
        $msg .= "_Choose your next action below 👇_";
        
        // Send commentary first, then send action buttons
        $this->sendText($phone, $msg);
        
        // Generate randomized action buttons
        $actionButtons = $this->generateRandomizedActionButtons($fixtureId);
        
        $header = "⚡ Quick Actions";
        $body = "Explore more about this match!";
        $footer = "Tap any option to dive deeper 🔍";
        
        $result = $this->messageSender->sendInteractiveButtons(
            $phone, 
            $header, 
            $body, 
            $footer, 
            $actionButtons
        );
        
        return $result['success'] ?? false;
    }
    
    /**
     * Generate randomized action buttons for commentary
     */
    protected function generateRandomizedActionButtons(string $fixtureId): array
    {
        // Randomized keywords for each action to keep it fresh
        $lineupsKeywords = ['👥 Lineups', '🚀 Starting XI', '⭐ Team Setup', '🎯 Formation'];
        $statsKeywords = ['📊 Stats', '📈 Numbers', '🔢 Data', '💯 Analysis'];
        $alertsKeywords = ['🔔 Alerts', '⚡ Notify', '📱 Updates', '🎯 Track'];
        
        // Select random keywords
        $lineupsKeyword = $lineupsKeywords[array_rand($lineupsKeywords)];
        $statsKeyword = $statsKeywords[array_rand($statsKeywords)];
        $alertsKeyword = $alertsKeywords[array_rand($alertsKeywords)];
        
        return [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'lineups',
                    'title' => $lineupsKeyword
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'stats',
                    'title' => $statsKeyword
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'alerts',
                    'title' => $alertsKeyword
                ]
            ]
        ];
    }
    
    /**
     * Generate randomized follow-up buttons
     */
    protected function generateFollowUpButtons(): array
    {
        $tableKeywords = ['📊 Table', '🏆 Rankings', '📈 Standings', '🎯 League'];
        $lineupsKeywords = ['👥 Lineups', '🚀 Teams', '⭐ Squads', '🎯 Players'];
        $menuKeywords = ['🏠 Menu', '🎯 Home', '⭐ Main', '🚀 Start'];
        
        $tableKeyword = $tableKeywords[array_rand($tableKeywords)];
        $lineupsKeyword = $lineupsKeywords[array_rand($lineupsKeywords)];
        $menuKeyword = $menuKeywords[array_rand($menuKeywords)];
        
        return [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'table',
                    'title' => $tableKeyword
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'lineups',
                    'title' => $lineupsKeyword
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'menu',
                    'title' => $menuKeyword
                ]
            ]
        ];
    }
    
    /**
     * Handle text commands with new menu system
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

        // Handle numbered menu options
        if ($textLower === '1' || $textLower === 'favorite' || $textLower === 'team') {
            $this->sendFavoriteTeamPrompt($subscriber->phone_number);
            return ['status' => 'favorite_prompt_sent'];
        }

        if ($textLower === '2' || $textLower === 'commentary' || $textLower === 'style') {
            $this->sendCommentaryStylePrompt($subscriber->phone_number, $subscriber);
            return ['status' => 'commentary_prompt_sent'];
        }

        if ($textLower === '3' || $textLower === 'schedule' || $textLower === 'matches') {
            $this->sendTodayResults($subscriber->phone_number);
            return ['status' => 'schedule_sent'];
        }

        if ($textLower === '4' || $textLower === 'help' || $textLower === 'commands') {
            $this->sendHelpCommands($subscriber->phone_number);
            return ['status' => 'help_sent'];
        }

        // Handle commentary style changes
        if ($textLower === 'digest' || $textLower === 'smart') {
            $subscriber->update(['commentary_mode' => 'digest']);
            $this->sendText($subscriber->phone_number, "🎯 *Smart Alerts Activated!*\n\nPerfect choice! You'll get the big moments without the noise. Stay focused on what matters most! 🏆\n\nType *menu* for more options");
            return ['status' => 'smart_set'];
        }

        if ($textLower === 'live') {
            $subscriber->update(['commentary_mode' => 'live']);
            $this->sendText($subscriber->phone_number, "⚡ *Live Updates Ready!*\n\nYou're all set for instant action! Never miss a goal or key moment again! ⚽\n\nType *menu* for more options");
            return ['status' => 'live_set'];
        }

        // Handle notifications on/off
        if ($textLower === 'notifications on' || $textLower === 'on' || $textLower === 'resume') {
            $subscriber->update(['notifications_enabled' => true]);
            $this->sendText($subscriber->phone_number, "🔔 *Welcome Back!*\n\nGreat to have you back! You're all set to catch every exciting moment! ⚽\n\nType *menu* to customize your experience");
            return ['status' => 'notifications_on'];
        }

        if ($textLower === 'notifications off' || $textLower === 'off' || $textLower === 'pause') {
            $subscriber->update(['notifications_enabled' => false]);
            $this->sendText($subscriber->phone_number, "⏸️ *Taking a break?*\n\nNo problem! We'll be here when you're ready for more football action. Just type *resume* anytime! 🏆\n\nMiss you already!");
            return ['status' => 'notifications_off'];
        }

        // Handle status request
        if ($textLower === 'status' || $textLower === 'my account') {
            $this->sendAccountStatus($subscriber);
            return ['status' => 'status_sent'];
        }

        // Handle menu/home
        if (in_array($textLower, ['menu', 'home', 'hi', 'hello', 'start'], true)) {
            $this->sendMainMenu($subscriber->phone_number);
            return ['status' => 'menu_sent'];
        }

        // Handle stop/pause
        if (in_array($textLower, ['stop', 'pause', 'unsubscribe'], true)) {
            $subscriber->update(['notifications_enabled' => false]);
            $this->sendText($subscriber->phone_number, "⏸️ *Notifications Paused*\n\nYou won't receive match updates.\nType *start* anytime to resume!");
            return ['status' => 'paused'];
        }

        // Check if it's a team name for favorite setting
        if ($this->isTeamName($textLower)) {
            $subscriber->update(['favorite_team' => ucfirst($text)]);
            $this->sendText($subscriber->phone_number, "🌟 *Excellent Choice!*\n\n" . ucfirst($text) . " is now YOUR team! You'll get special alerts the moment they score! 🏆\n\nType *menu* to explore more features");
            return ['status' => 'favorite_set'];
        }

        // Handle "next [team]" e.g. "next brazil"
        if (str_starts_with($textLower, 'next ')) {
            $team = trim(substr($text, 5));
            $this->sendTeamNextMatch($subscriber->phone_number, $team);
            return ['status' => 'next_match_sent'];
        }

        // Route other commands
        if (in_array($textLower, ['demo'], true)) {
            $this->sendText($subscriber->phone_number, "🎬 Demo mode removed! You now have full tournament access.\n\nType *menu* to see your options.");
            return ['status' => 'demo_removed'];
        }

        if (in_array($textLower, ['subscribe', 'opt in', 'join'], true)) {
            $this->sendText($subscriber->phone_number, "✅ You're already subscribed with full tournament access!\n\nType *menu* to customize your experience.");
            return ['status' => 'already_subscribed'];
        }

        if (in_array($textLower, ['pricing', 'price'], true)) {
            $this->sendText($subscriber->phone_number, "🎉 Good news! You have full tournament access at no cost.\n\nType *menu* to customize your experience.");
            return ['status' => 'free_access'];
        }

        if (in_array($textLower, ['table', 'standings', 'groups'], true)) {
            $this->sendStandings($subscriber->phone_number);
            return ['status' => 'standings_sent'];
        }

        if (in_array($textLower, ['results', 'scores', 'today'], true)) {
            $this->sendTodayResults($subscriber->phone_number);
            return ['status' => 'results_sent'];
        }

        if (in_array($textLower, ['upcoming', 'schedule', 'fixtures'], true)) {
            $this->sendUpcoming($subscriber->phone_number);
            return ['status' => 'upcoming_sent'];
        }

        if ($textLower === 'next') {
            $this->sendTeamNextMatch($subscriber->phone_number, $subscriber->favorite_team ?? '');
            return ['status' => 'next_sent'];
        }

        if (in_array($textLower, ['lineups', 'lineup', 'starting 11', 'xi'], true)) {
            $this->sendLineups($subscriber->phone_number);
            return ['status' => 'lineups_sent'];
        }

        if (in_array($textLower, ['stats', 'statistics', 'live stats'], true)) {
            $this->sendLiveStats($subscriber->phone_number);
            return ['status' => 'stats_sent'];
        }

        if (in_array($textLower, ['subs', 'substitutions', 'changes'], true)) {
            $this->sendSubstitutions($subscriber->phone_number);
            return ['status' => 'subs_sent'];
        }

        // Route everything else to Claude AI with full RAG context
        return $this->handleAIQuestion($subscriber, $text);
    }

    /**
     * Send help commands with buttons
     */
    protected function sendHelpCommands(string $phone): bool
    {
        $header = "🤖 Everything You Can Do";
        $body = "Explore all the football features at your fingertips!";
        $footer = "Just tap a button - no typing needed!";
        
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'table',
                    'title' => '📊 Check Table'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'lineups',
                    'title' => '👥 View Lineups'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'stats',
                    'title' => '📈 Live Stats'
                ]
            ]
        ];
        
        $result = $this->messageSender->sendInteractiveButtons(
            $phone, 
            $header, 
            $body, 
            $footer, 
            $buttons
        );
        
        return $result['success'] ?? false;
    }

    /**
     * Send account status
     */
    protected function sendAccountStatus(Subscriber $subscriber): bool
    {
        $status = $subscriber->notifications_enabled ? 'ON' : 'OFF';
        $style = ucfirst($subscriber->commentary_mode ?? 'digest');
        $team = $subscriber->favorite_team ?? 'All teams';
        $type = $subscriber->subscription_type ?? 'full_tournament';
        
        $message = "📊 *Your GoalBot Status*\n\n";
        $message .= "🔔 *Notifications:* {$status}\n";
        $message .= "📝 *Style:* {$style}\n";
        $message .= "🌟 *Favorite:* {$team}\n";
        $message .= "🎫 *Plan:* {$type}\n\n";
        $message .= "Type *menu* to change any setting";
        
        return $this->sendText($subscriber->phone_number, $message);
    }

    /**
     * Check if message is a team name
     */
    protected function isTeamName(string $message): bool
    {
        $commonTeams = [
            'brazil', 'argentina', 'france', 'spain', 'germany', 'england',
            'portugal', 'netherlands', 'belgium', 'italy', 'croatia', 'uruguay',
            'mexico', 'usa', 'canada', 'japan', 'south korea', 'australia',
            'morocco', 'senegal', 'ghana', 'nigeria', 'cameroon', 'tunisia',
            'egypt', 'qatar', 'saudi arabia', 'iran', 'iraq', 'jordan'
        ];
        
        return in_array(strtolower($message), $commonTeams);
    }

    /**
     * Send substitutions
     */
    protected function sendSubstitutions(string $phone): bool
    {
        $football = app(\App\Services\Football\FootballDataService::class);
        $live = $football->getLiveMatches();
        $today = $football->getMatchesForDate(now()->toDateString());
        $matches = !empty($live) ? $live : $today;

        if (empty($matches)) {
            return $this->sendText($phone, "🔄 No match data available right now.");
        }

        $match = $matches[0];
        $fixtureId = $match['fixture']['id'];
        $home = $match['teams']['home']['name'];
        $away = $match['teams']['away']['name'];

        $events = $football->getMatchEvents($fixtureId);
        $subs = collect($events)->where('type', 'subst')->sortBy('time.elapsed');

        if ($subs->isEmpty()) {
            return $this->sendText($phone, "🔄 No substitutions yet in {$home} vs {$away}.");
        }

        $msg = "🔄 *Substitutions — {$home} vs {$away}*\n\n";
        foreach ($subs as $s) {
            $teamName = $s['team']['name'];
            $out = $s['player']['name'] ?? '?';
            $in  = $s['assist']['name'] ?? '?';
            $min = $s['time']['elapsed'] ?? '?';
            $msg .= "{$min}' | {$teamName}\n⬆ {$in}  ⬇ {$out}\n\n";
        }

        return $this->sendText($phone, trim($msg));
    }

    protected function sendStandings(string $phone): bool
    {
        $football = app(\App\Services\Football\FootballDataService::class);
        $groups = $football->getStandings();

        if (empty($groups)) {
            return $this->sendText($phone, "⚽ Standings not available yet — group stage hasn't started. Reply *upcoming* to see fixtures!");
        }

        $msg = "🏆 *World Cup 2026 Standings*\n\n";
        foreach ($groups as $group) {
            $groupName = $group[0]['group'] ?? 'Group';
            $msg .= "*{$groupName}*\n";
            foreach ($group as $i => $team) {
                $pos = $i + 1;
                $flag = $pos <= 2 ? '🟢' : '⚪';
                $msg .= "{$flag} {$pos}. {$team['team']['name']} — {$team['points']}pts ({$team['all']['win']}W {$team['all']['draw']}D {$team['all']['lose']}L)\n";
            }
            $msg .= "\n";
        }
        $msg .= "_Reply *results* for today's scores_";

        // Send standings first, then subscription prompt
        $this->sendText($phone, $msg);

        $header = "🔔 Never Miss a Goal";
        $body = "Get instant alerts the moment a goal goes in — for every match, straight to WhatsApp.";
        $footer = "Join thousands of fans already getting alerts ⚡";

        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'subscribe_alerts',
                    'title' => '⚽ Yes, Alert Me!'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'schedule',
                    'title' => '� Today\'s Matches'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'menu',
                    'title' => '🏠 Main Menu'
                ]
            ]
        ];
        
        $result = $this->messageSender->sendInteractiveButtons(
            $phone, 
            $header, 
            $body, 
            $footer, 
            $buttons
        );
        
        return $result['success'] ?? false;
    }

    protected function sendTodayResults(string $phone): bool
    {
        $football = app(\App\Services\Football\FootballDataService::class);
        $matches = $football->getMatchesForDate(now()->toDateString());

        if (empty($matches)) {
            $upcoming = $football->getUpcomingMatches(1);
            if (!empty($upcoming)) {
                $next = $upcoming[0];
                $kickoff = \Carbon\Carbon::parse($next['fixture']['date'])->setTimezone('Africa/Nairobi')->format('H:i');
                $home = $next['teams']['home']['name'];
                $away = $next['teams']['away']['name'];
                
                // Send next match as interactive button
                $header = "📋 Next Match Today";
                $body = "⏰ *{$home} vs {$away}* at {$kickoff} EAT\n\nGet ready for the action!";
                $footer = "Tap to explore this match 👇";
                
                $buttons = [
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'match_' . $next['fixture']['id'],
                            'title' => "⚽ {$home} vs {$away}"
                        ]
                    ],
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'upcoming',
                            'title' => '📅 Full Schedule'
                        ]
                    ],
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'menu',
                            'title' => '🏠 Main Menu'
                        ]
                    ]
                ];
                
                $result = $this->messageSender->sendInteractiveButtons(
                    $phone, 
                    $header, 
                    $body, 
                    $footer, 
                    $buttons
                );
                
                return $result['success'] ?? false;
            }
            return $this->sendText($phone, "📋 No matches today. Reply *upcoming* for the next fixtures.");
        }

        // Build list message rows — one per match, plus a subscribe row
        $rows = [];
        foreach (array_slice($matches, 0, 9) as $m) {
            $home    = $m['teams']['home']['name'];
            $away    = $m['teams']['away']['name'];
            $hg      = $m['goals']['home'] ?? '-';
            $ag      = $m['goals']['away'] ?? '-';
            $status  = $m['fixture']['status']['short'];
            $elapsed = $m['fixture']['status']['elapsed'];

            $statusLabel = match ($status) {
                '1H', '2H' => "🔴 LIVE {$elapsed}'",
                'HT'        => '⏸ Half Time',
                'FT'        => '✅ Full Time',
                'AET'       => '✅ AET',
                'PEN'       => '✅ Penalties',
                'NS'        => '⏰ ' . \Carbon\Carbon::parse($m['fixture']['date'])->timezone('Africa/Nairobi')->format('H:i') . ' EAT',
                default     => $status,
            };

            // WhatsApp list row title max = 24 chars
            $maxEach = 10;
            $rowTitle = substr($home, 0, $maxEach) . ' v ' . substr($away, 0, $maxEach);

            $rows[] = [
                'id'          => 'match_' . $m['fixture']['id'],
                'title'       => $rowTitle,
                'description' => "{$home} {$hg}–{$ag} {$away}  |  {$statusLabel}",
            ];
        }

        $sections = [
            ['title' => "📅 Today's Matches", 'rows' => $rows],
            ['title' => '🔔 Alerts', 'rows' => [
                ['id' => 'subscribe_alerts', 'title' => '⚽ Get Goal Alerts', 'description' => 'Instant alerts every time a goal goes in'],
                ['id' => 'table',            'title' => '🏆 Group Standings',  'description' => 'See who\'s top of each group'],
            ]],
        ];

        $result = $this->messageSender->sendListMessage(
            $phone,
            '⚽ FIFA World Cup 2026',
            'All of today\'s matches. Tap one to explore lineups, stats, and live commentary.',
            'You can also just type any question!',
            '📋 View Matches',
            $sections
        );

        return $result['success'] ?? false;
    }

    protected function sendUpcoming(string $phone): bool
    {
        $football = app(\App\Services\Football\FootballDataService::class);
        $matches = $football->getUpcomingMatches(3);

        if (empty($matches)) {
            return $this->sendText($phone, "📅 No upcoming matches in the next 3 days. The tournament may be on a break!");
        }

        $msg = "📅 *Upcoming Fixtures*\n\n";
        $lastDate = '';
        foreach (array_slice($matches, 0, 8) as $m) {
            $dt = \Carbon\Carbon::parse($m['fixture']['date'])->setTimezone('Africa/Nairobi');
            $date = $dt->format('D d M');
            $time = $dt->format('H:i');
            $home = $m['teams']['home']['name'];
            $away = $m['teams']['away']['name'];
            $round = $m['league']['round'] ?? '';

            if ($date !== $lastDate) {
                $msg .= "\n*{$date}*\n";
                $lastDate = $date;
            }
            $msg .= "⏰ {$time} EAT — {$home} vs {$away}\n";
        }
        $msg .= "\n_Reply *results* for today's scores | *table* for standings_";
        return $this->sendText($phone, $msg);
    }

    protected function sendTeamNextMatch(string $phone, string $team): bool
    {
        if (empty(trim($team))) {
            return $this->sendText($phone, "⚽ Which team? Try: *next Brazil* or *next Kenya*");
        }

        $football = app(\App\Services\Football\FootballDataService::class);
        $match = $football->getTeamNextMatch($team);

        if (!$match) {
            return $this->sendText($phone, "🔍 No upcoming match found for *{$team}*. They may be eliminated or the team name may differ. Try the full name!");
        }

        $home = $match['teams']['home']['name'];
        $away = $match['teams']['away']['name'];
        $dt = \Carbon\Carbon::parse($match['fixture']['date'])->setTimezone('Africa/Nairobi');
        $venue = $match['fixture']['venue']['name'] ?? 'TBD';
        $city = $match['fixture']['venue']['city'] ?? '';
        $round = $match['league']['round'] ?? '';
        $diff = now()->diffForHumans($dt, ['parts' => 2]);

        $msg = "📅 *{$home} vs {$away}*\n\n";
        $msg .= "🗓 {$dt->format('D d M Y')}\n";
        $msg .= "⏰ {$dt->format('H:i')} EAT ({$diff})\n";
        $msg .= "🏟 {$venue}, {$city}\n";
        $msg .= "🏆 {$round}\n\n";
        $msg .= "_Reply *subscribe* to get live alerts for this match!_";

        return $this->sendText($phone, $msg);
    }

    /**
     * Handle free-form questions about World Cup using Claude AI
     */
    protected function handleAIQuestion(Subscriber $subscriber, string $question): array
    {
        $answer = $this->askClaude($question, $subscriber->phone_number);
        $this->sendText($subscriber->phone_number, $answer);

        // Save to conversation history for follow-up context
        $this->appendConversation($subscriber->phone_number, $question, $answer);

        // After 2nd AI question, introduce subscription — user is clearly engaged
        $aiCount = \Illuminate\Support\Facades\Cache::increment("ai_count_{$subscriber->phone_number}");
        if ($aiCount === 2) {
            $this->messageSender->sendInteractiveButtons(
                $subscriber->phone_number,
                "🔔 Want Live Alerts?",
                "You're clearly into this! Get goal alerts the moment they happen — every match, all tournament.",
                "Free for the whole tournament ⚡",
                [
                    ['type' => 'reply', 'reply' => ['id' => 'subscribe_alerts', 'title' => '⚽ Yes, Alert Me!']],
                    ['type' => 'reply', 'reply' => ['id' => 'schedule',         'title' => '📅 Today\'s Matches']],
                ]
            );
        }

        return ['status' => 'ai_answered'];
    }

    /**
     * Get recent conversation history for a user
     */
    protected function getConversationHistory(string $phoneNumber): array
    {
        return \Illuminate\Support\Facades\Cache::get("ai_chat_{$phoneNumber}", []);
    }

    /**
     * Append a Q&A turn to conversation history (keeps last 6 messages = 3 turns)
     */
    protected function appendConversation(string $phoneNumber, string $userMsg, string $assistantMsg): void
    {
        $history = $this->getConversationHistory($phoneNumber);
        $history[] = ['role' => 'user', 'content' => $userMsg];
        $history[] = ['role' => 'assistant', 'content' => $assistantMsg];
        // Keep only last 6 messages (3 turns) to manage context size
        $history = array_slice($history, -6);
        \Illuminate\Support\Facades\Cache::put("ai_chat_{$phoneNumber}", $history, now()->addMinutes(30));
    }

    /**
     * Ask Claude a World Cup question (RAG: includes live fixtures + conversation history)
     */
    protected function askClaude(string $question, ?string $phoneNumber = null): string
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

        $systemPrompt = "You are GoalBot, a WhatsApp football assistant for FIFA World Cup 2026. " .
            "You have real-time match data below — always use it before relying on general knowledge. " .
            "Channel the energy and poetry of Peter Drury when describing match moments.\n\n" .
            "=== LIVE MATCH DATA ===\n" .
            "(Includes: fixtures, scores, lineups, live events, live stats, minute-by-minute commentary, odds, predictions, injuries)\n" .
            $fixturesContext .
            "\n=== END LIVE MATCH DATA ===\n\n" .
            "Rules:\n" .
            "- ALWAYS consult the LIVE MATCH DATA above first for any question about scores, lineups, stats, odds or match events\n" .
            "- LIVE COMMENTARY section: use it to describe what has happened in the match — quote key moments dramatically\n" .
            "- LINEUPS section: answer who is playing, formations, who is on the bench\n" .
            "- LIVE STATS section: answer possession, shots, corners questions\n" .
            "- ODDS/PREDICTIONS section: reference for betting or prediction questions\n" .
            "- INJURIES section: mention injuries when asked about squad availability\n" .
            "- Convert UTC times (Kenya UTC+3, Mexico City UTC-6)\n" .
            "- Keep replies under 600 characters (WhatsApp friendly)\n" .
            "- Use 1-3 emojis max\n" .
            "- Be direct and energetic — no waffle\n" .
            "- If data doesn't cover the answer, say so honestly\n" .
            "- Redirect non-football questions back to the match";

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(20)->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-haiku-4-5'),
                'max_tokens' => 600,
                'system' => $systemPrompt,
                'messages' => array_merge(
                    $phoneNumber ? $this->getConversationHistory($phoneNumber) : [],
                    [['role' => 'user', 'content' => $question]]
                ),
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
