<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\WhatsApp\MessageSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    private MessageSender $whatsapp;
    
    public function __construct(MessageSender $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }
    
    /**
     * Handle incoming WhatsApp messages
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();

        // Log raw JSON to avoid Laravel's depth-limit normalizer truncating error objects
        Log::info('WhatsApp webhook received', ['raw' => json_encode($payload)]);

        // Extract message data
        $entry   = $payload['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value   = $changes['value'] ?? null;

        // ── Handle delivery status callbacks ──────────────────────────────
        if (!empty($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->handleDeliveryStatus($status);
            }
            return response()->json(['status' => 'received']);
        }

        $messageData = $value['messages'][0] ?? null;

        if (!$messageData) {
            return response()->json(['status' => 'received']);
        }
        
        $from        = $messageData['from'] ?? null;
        $messageType = $messageData['type'] ?? null;
        $messageBody = strtolower(trim($messageData['text']['body'] ?? ''));

        // Stamp last inbound message time — reopens the 24h window tracking
        if ($from) {
            Subscriber::where('phone_number', $this->normalizePhoneNumber($from))
                ->update([
                    'last_message_in_at' => now(),
                    'window_failed'      => false,
                ]);
        }

        if ($from && ($messageBody || $messageType === 'interactive')) {
            $this->processIncomingMessage($from, $messageBody, $messageData);
        }
        
        // Always return 200 quickly to acknowledge
        return response()->json(['status' => 'received']);
    }
    
    /**
     * Handle a WhatsApp delivery status callback.
     * Error 131026 = message outside 24h window.
     */
    private function handleDeliveryStatus(array $status): void
    {
        $recipient = $status['recipient_id'] ?? null;
        $state     = $status['status'] ?? null;
        $errors    = $status['errors'] ?? [];

        if ($state === 'failed' && $recipient) {
            // Extract numeric error codes
            $codes = array_map(fn($e) => is_array($e) ? ($e['code'] ?? null) : null, $errors);
            $codes = array_filter($codes);

            Log::warning('WhatsApp delivery failed', [
                'recipient' => $recipient,
                'error_codes' => array_values($codes),
                'errors' => $errors,
            ]);

            // 131026 = user hasn't messaged in last 24h (session window closed)
            if (in_array(131026, $codes, true)) {
                Log::warning('24h window closed for user', ['phone' => $recipient]);
                Subscriber::where('phone_number', $recipient)
                    ->update(['window_failed' => true]);
            }
        }
    }

    /**
     * Process an incoming message with smooth onboarding and menu system
     */
    private function processIncomingMessage(string $phoneNumber, string $message, array $messageData = []): void
    {
        // Normalize phone number
        $cleanNumber = $this->normalizePhoneNumber($phoneNumber);

        // Find or create subscriber
        $subscriber = Subscriber::firstOrCreate(
            ['phone_number' => $cleanNumber],
            [
                'notifications_enabled' => true,
                'notify_all_matches' => true,
                'timezone' => 'UTC',
                'commentary_mode' => 'digest',
                'is_active' => true,
                'subscription_type' => 'full_tournament',
            ]
        );
        
        // Extract attribution token "r=123" from prefilled CTA message (best-effort)
        if (preg_match('/\br=(\d+)\b/', $message, $m)) {
            $visit = \App\Models\LandingVisit::find((int) $m[1]);
            if ($visit && $subscriber->wasRecentlyCreated) {
                $subscriber->update([
                    'utm_source' => $visit->utm_source,
                    'utm_medium' => $visit->utm_medium,
                    'utm_campaign' => $visit->utm_campaign,
                    'utm_term' => $visit->utm_term,
                    'utm_content' => $visit->utm_content,
                    'attribution_ip' => $visit->ip,
                    'country' => $visit->country,
                ]);
            }
        }
        
        Log::info('Subscriber interaction', [
            'phone' => $cleanNumber,
            'message' => $message,
            'is_new' => $subscriber->wasRecentlyCreated,
        ]);
        
        // Handle new user onboarding
        if ($subscriber->wasRecentlyCreated) {
            $this->sendWelcomeMessage($cleanNumber);
            return;
        }
        
        // Process commands and menu options
        $this->processCommand($subscriber, $message);
    }
    
    /**
     * Send smooth welcome message to new users
     */
    private function sendWelcomeMessage(string $phoneNumber): void
    {
        $this->whatsapp->sendMainMenu($phoneNumber);
    }
    
    /**
     * Process user commands and menu choices
     */
    private function processCommand(Subscriber $subscriber, string $message): void
    {
        // Handle menu numbers
        switch ($message) {
            case '1':
            case 'favorite':
            case 'team':
                $this->handleFavoriteTeam($subscriber);
                break;
                
            case '2':
            case 'commentary':
            case 'style':
                $this->handleCommentaryStyle($subscriber);
                break;
                
            case '3':
            case 'schedule':
            case 'matches':
                $this->handleSchedule($subscriber);
                break;
                
            case '4':
            case 'help':
            case 'commands':
                $this->handleHelp($subscriber);
                break;
                
            case 'menu':
            case 'home':
                $this->sendMainMenu($subscriber);
                break;
                
            case 'stop':
            case 'unsubscribe':
            case 'off':
                $this->handleStop($subscriber);
                break;
                
            case 'start':
            case 'on':
                $this->handleStart($subscriber);
                break;
                
            case 'status':
            case 'my account':
                $this->handleStatus($subscriber);
                break;
                
            case 'digest':
                $this->setCommentaryStyle($subscriber, 'digest');
                break;
                
            case 'live':
                $this->setCommentaryStyle($subscriber, 'live');
                break;
                
            case 'on':
            case 'notifications on':
                $subscriber->update(['notifications_enabled' => true]);
                $this->whatsapp->sendMessage($subscriber->phone_number, "🔔 *Notifications Enabled*\n\nYou'll receive match updates again!");
                break;
                
            case 'off':
            case 'notifications off':
                $subscriber->update(['notifications_enabled' => false]);
                $this->whatsapp->sendMessage($subscriber->phone_number, "🔕 *Notifications Disabled*\n\nType *start* to resume anytime.");
                break;
                
            default:
                // Check if it's a team name for favorite setting
                if ($this->isTeamName($message)) {
                    $this->setFavoriteTeam($subscriber, $message);
                } else {
                    $this->handleUnknown($subscriber);
                }
                break;
        }
    }
    
    /**
     * Handle favorite team selection
     */
    private function handleFavoriteTeam(Subscriber $subscriber): void
    {
        $message = "🌟 *Choose Your Favorite Team*\n\n";
        $message .= "Type your team name (e.g., Brazil, England, France)\n\n";
        $message .= "Popular teams:\n";
        $message .= "🇧🇷 Brazil • 🇦🇷 Argentina • 🇫🇷 France\n";
        $message .= "🇪🇸 Spain • 🇩🇪 Germany • 🏴󠁧󠁢󠁥󠁮󠁧󠁿 England\n";
        $message .= "🇵🇹 Portugal • 🇳🇱 Netherlands • 🇧🇪 Belgium\n\n";
        $message .= "Reply with your team name or *menu* to go back";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Handle commentary style selection
     */
    private function handleCommentaryStyle(Subscriber $subscriber): void
    {
        $current = $subscriber->commentary_mode ?? 'digest';
        $status = $subscriber->notifications_enabled ? 'ON' : 'OFF';
        
        $message = "⚙️ *Commentary Preferences*\n\n";
        $message .= "📊 *Current Status:* {$status}\n";
        $message .= "📝 *Style:* " . ucfirst($current) . "\n\n";
        $message .= "*Choose your style:*\n";
        $message .= "📋 *Digest* - 3-minute summaries (recommended)\n";
        $message .= "⚡ *Live* - Every update instantly\n\n";
        $message .= "Reply: *digest* or *live*\n";
        $message .= "Or: *notifications on/off*\n";
        $message .= "Type *menu* to go back";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Handle match schedule request
     */
    private function handleSchedule(Subscriber $subscriber): void
    {
        // Get today's matches
        $football = app(\App\Services\Football\FootballDataService::class);
        $matches = $football->getMatchesForDate(now()->toDateString());
        
        $message = "📅 *Today's World Cup Matches*\n\n";
        
        if (empty($matches)) {
            $message .= "No matches scheduled for today.";
        } else {
            foreach ($matches as $match) {
                $home = $match['teams']['home']['name'];
                $away = $match['teams']['away']['name'];
                $time = $match['fixture']['date'] ? date('H:i', strtotime($match['fixture']['date'])) : 'TBD';
                $status = $match['fixture']['status']['long'] ?? 'Scheduled';
                
                $emoji = $status === 'Live' ? '🔴' : ($status === 'Finished' ? '✅' : '⏰');
                $message .= "{$emoji} *{$home}* vs *{$away}*\n";
                $message .= "   🕐 {$time} • {$status}\n\n";
            }
        }
        
        $message .= "Type *menu* for more options";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Handle help and commands
     */
    private function handleHelp(Subscriber $subscriber): void
    {
        $message = "🤖 *GoalBot Commands*\n\n";
        $message .= "*Quick Menu:*\n";
        $message .= "1️⃣ *Favorite team* - Set your team\n";
        $message .= "2️⃣ *Commentary style* - Digest or Live\n";
        $message .= "3️⃣ *Schedule* - Today's matches\n";
        $message .= "4️⃣ *Status* - Your settings\n\n";
        $message .= "*Other Commands:*\n";
        $message .= "• *menu* - Show main menu\n";
        $message .= "• *stop* - Pause notifications\n";
        $message .= "• *start* - Resume notifications\n";
        $message .= "• Type any team name to set as favorite\n\n";
        $message .= "Need help? Reply with your question!";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Send main menu
     */
    private function sendMainMenu(Subscriber $subscriber): void
    {
        $message = "⚽ *GoalBot Menu*\n\n";
        $message .= "What would you like to do?\n\n";
        $message .= "1️⃣ *Favorite team*\n";
        $message .= "2️⃣ *Commentary style*\n";
        $message .= "3️⃣ *Today's matches*\n";
        $message .= "4️⃣ *Help & commands*\n\n";
        $message .= "Reply with a number or type *menu* anytime";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Handle stop/unsubscribe
     */
    private function handleStop(Subscriber $subscriber): void
    {
        $subscriber->update(['notifications_enabled' => false]);
        
        $message = "⏸️ *Notifications Paused*\n\n";
        $message .= "You won't receive match updates.\n";
        $message .= "Type *start* anytime to resume!\n\n";
        $message .= "We'll miss you! ⚽";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Handle start/resume
     */
    private function handleStart(Subscriber $subscriber): void
    {
        $subscriber->update(['notifications_enabled' => true]);
        
        $message = "▶️ *Notifications Resumed!*\n\n";
        $message .= "Welcome back! You'll receive:\n";
        $message .= "📋 Match summaries every 3 minutes\n";
        $message .= "⚡ Live goals and key moments\n\n";
        $message .= "Type *menu* to change settings";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Handle status request
     */
    private function handleStatus(Subscriber $subscriber): void
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
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Handle unknown command
     */
    private function handleUnknown(Subscriber $subscriber): void
    {
        $message = "🤔 I didn't understand that.\n\n";
        $message .= "Try these options:\n";
        $message .= "• Reply *menu* for main menu\n";
        $message .= "• Type a number 1-4\n";
        $message .= "• Type *help* for commands\n";
        $message .= "• Type a team name (e.g., Brazil)";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Set favorite team
     */
    private function setFavoriteTeam(Subscriber $subscriber, string $teamName): void
    {
        $subscriber->update(['favorite_team' => ucfirst($teamName)]);
        
        $message = "🌟 *Favorite Team Set!*\n\n";
        $message .= "You'll get extra updates for *{$teamName}*!\n\n";
        $message .= "Type *menu* for more options";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Set commentary style
     */
    private function setCommentaryStyle(Subscriber $subscriber, string $style): void
    {
        $subscriber->update(['commentary_mode' => $style]);
        
        $description = $style === 'live' ? 'every update instantly' : '3-minute summaries';
        $emoji = $style === 'live' ? '⚡' : '📋';
        
        $message = "{$emoji} *Commentary Style Updated!*\n\n";
        $message .= "You'll now receive {$description}.\n\n";
        $message .= "Type *menu* for more options";
        
        $this->whatsapp->sendMessage($subscriber->phone_number, $message);
    }
    
    /**
     * Check if message is a team name
     */
    private function isTeamName(string $message): bool
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
    
    private function normalizePhoneNumber(string $number): string
    {
        // Remove spaces, dashes, plus
        $cleaned = preg_replace('/[^0-9]/', '', $number);
        
        // Add country code if missing
        if (strlen($cleaned) <= 10 && !str_starts_with($cleaned, '254')) {
            $cleaned = '254' . ltrim($cleaned, '0');
        }
        
        return $cleaned;
    }
}
