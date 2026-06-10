<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    /**
     * Handle incoming WhatsApp messages
     * 
     * For MVP, we just acknowledge receipt and log for later analysis.
     * Future phases will add interactive features.
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();
        
        // Log incoming webhook for debugging
        Log::info('WhatsApp webhook received', $payload);
        
        // Extract message data
        $entry = $payload['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $messageData = $value['messages'][0] ?? null;
        
        if (!$messageData) {
            // Could be a status update or other event
            return response()->json(['status' => 'received']);
        }
        
        $from = $messageData['from'] ?? null;
        $messageType = $messageData['type'] ?? null;
        $messageBody = $messageData['text']['body'] ?? null;
        
        if ($from && $messageBody) {
            $this->processIncomingMessage($from, $messageBody);
        }
        
        // Always return 200 quickly to acknowledge
        return response()->json(['status' => 'received']);
    }
    
    /**
     * Process an incoming message
     * 
     * MVP: Just register new subscribers
     * Future: Handle commands like /favorite, /stop, etc.
     */
    private function processIncomingMessage(string $phoneNumber, string $message): void
    {
        // Normalize phone number
        $cleanNumber = $this->normalizePhoneNumber($phoneNumber);
        
        // Find or create subscriber
        $subscriber = Subscriber::firstOrCreate(
            ['phone_number' => $cleanNumber],
            [
                'notifications_enabled' => true,
                'notify_all_matches' => false,
                'timezone' => 'UTC',
            ]
        );
        
        Log::info('Subscriber interaction', [
            'phone' => $cleanNumber,
            'message' => $message,
            'is_new' => $subscriber->wasRecentlyCreated,
        ]);
        
        // TODO: Future phases will parse commands
        // - /favorite [team]
        // - /stop
        // - /live
        // - /schedule
        
        // For now, just log it
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
