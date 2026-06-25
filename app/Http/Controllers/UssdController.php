<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Services\Sms\SmsService;
use App\Services\WhatsApp\MessageSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles USSD callbacks from Wasiliana (Africa's Talking-compatible format).
 *
 * Flow:
 *   1. User dials the USSD code (e.g. *384*123#)
 *   2. Wasiliana POSTs to https://goalbot.chat/ussd
 *   3. We immediately END the session with a friendly screen
 *   4. We create/update the subscriber record in the background
 *   5. We send an SMS with a WhatsApp deep-link
 *   6. We attempt a WhatsApp template message to open the conversation
 *
 * Wasiliana POST fields (AT-compatible):
 *   sessionId, phoneNumber, networkCode, serviceCode, text
 *
 * Response format:
 *   END <message>  — terminates the session
 *   CON <message>  — continues the session (prompts for more input)
 */
class UssdController extends Controller
{
    private const WA_NUMBER = '254715333355';
    private const WA_LINK   = 'https://wa.me/254715333355';

    public function __construct(
        private readonly SmsService    $sms,
        private readonly MessageSender $whatsapp,
    ) {}

    /**
     * Main USSD entry point.
     */
    public function handle(Request $request): \Illuminate\Http\Response
    {
        $sessionId   = $request->input('sessionId', '');
        $phoneNumber = $request->input('phoneNumber', '');
        $text        = trim($request->input('text', ''));

        Log::info('USSD request received', [
            'sessionId'   => $sessionId,
            'phoneNumber' => $phoneNumber,
            'serviceCode' => $request->input('serviceCode'),
            'text'        => $text,
        ]);

        $phone = $this->normalizePhone($phoneNumber);

        // Onboard the subscriber and dispatch WhatsApp/SMS in the background
        $this->onboardSubscriber($phone);

        // Always END immediately — one-step experience
        return $this->endSession(
            "Welcome to GoalBot! ⚽\n" .
            "Live football scores & alerts on WhatsApp.\n\n" .
            "Check your WhatsApp for a message from us to get started!\n\n" .
            "Or open: wa.me/" . self::WA_NUMBER
        );
    }

    /**
     * Create/update subscriber, send SMS + WhatsApp template.
     */
    private function onboardSubscriber(string $phone): void
    {
        $isNew = !Subscriber::where('phone_number', $phone)->exists();

        $subscriber = Subscriber::firstOrCreate(
            ['phone_number' => $phone],
            [
                'notifications_enabled' => true,
                'notify_all_matches'    => true,
                'timezone'              => 'Africa/Nairobi',
                'commentary_mode'       => 'digest',
                'is_active'             => true,
                'subscription_type'     => 'full_tournament',
                'utm_source'            => 'ussd',
                'utm_medium'            => 'ussd',
                'utm_campaign'          => 'ussd_onboard',
            ]
        );

        if (!$isNew) {
            // Re-activate if they had previously stopped
            if (!$subscriber->notifications_enabled || !$subscriber->is_active) {
                $subscriber->update([
                    'notifications_enabled' => true,
                    'is_active'             => true,
                ]);
                Log::info('USSD: Reactivated existing subscriber', ['phone' => $phone]);
            } else {
                Log::info('USSD: Existing subscriber dialled USSD', ['phone' => $phone]);
            }
        } else {
            Log::info('USSD: New subscriber onboarded', ['phone' => $phone]);
        }

        // 1. Send SMS with WhatsApp deep-link
        $this->sendOnboardingSms($phone, $isNew);

        // 2. Send WhatsApp template message to open the conversation
        $this->sendWhatsAppWelcome($phone, $isNew);
    }

    /**
     * Send the SMS directing the user to WhatsApp.
     */
    private function sendOnboardingSms(string $phone, bool $isNew): void
    {
        $message = $isNew
            ? "Hi! You've joined GoalBot ⚽\nGet live football scores & alerts on WhatsApp.\n\nOpen WhatsApp now: " . self::WA_LINK . "\n\nReply STOP to opt out."
            : "Welcome back to GoalBot ⚽\nYour live football updates are active.\n\nOpen WhatsApp: " . self::WA_LINK;

        $sent = $this->sms->send($phone, $message);

        Log::info('USSD onboarding SMS', [
            'phone'  => $phone,
            'is_new' => $isNew,
            'sent'   => $sent,
        ]);
    }

    /**
     * Send a WhatsApp template message to open the conversation window.
     * Uses the hello_world template as a fallback if no custom one exists.
     */
    private function sendWhatsAppWelcome(string $phone, bool $isNew): void
    {
        // Try sending a custom welcome template first; fall back to hello_world
        $template = $isNew ? 'goalbot_welcome' : 'goalbot_reactivation';
        $sent     = $this->whatsapp->sendTemplate($phone, $template);

        if (!$sent) {
            // Fallback to generic hello_world to open the window
            $sent = $this->whatsapp->sendTemplate($phone, 'hello_world');
        }

        Log::info('USSD WhatsApp template', [
            'phone'    => $phone,
            'template' => $template,
            'sent'     => $sent,
        ]);
    }

    /**
     * Return an END response to Wasiliana.
     */
    private function endSession(string $message): \Illuminate\Http\Response
    {
        return response('END ' . $message, 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Normalize phone to local format (e.g. 254712345678).
     */
    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Strip leading + or country code 254 → keep as 254XXXXXXXXX
        if (str_starts_with($cleaned, '254')) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '0')) {
            return '254' . substr($cleaned, 1);
        }

        return $cleaned;
    }
}
