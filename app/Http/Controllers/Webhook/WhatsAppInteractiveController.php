<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppInteractiveController extends Controller
{
    protected WhatsAppService $whatsappService;
    
    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }
    
    /**
     * Handle incoming WhatsApp webhook
     * Extracts message and routes to appropriate handler
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        Log::info('WhatsApp webhook received', ['raw' => json_encode($payload)]);

        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];

        // ── Delivery status callbacks ─────────────────────────────────────
        if (!empty($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->handleDeliveryStatus($status);
            }
            return response()->json(['status' => 'received']);
        }

        // ── Stamp 24h window on inbound messages ─────────────────────────
        $from = $value['messages'][0]['from'] ?? null;
        if ($from) {
            Subscriber::where('phone_number', $from)
                ->update(['last_message_in_at' => now(), 'window_failed' => false]);
        }

        // Process message through service layer
        $result = $this->whatsappService->handleIncomingMessage($value);

        return response()->json($result);
    }

    private function handleDeliveryStatus(array $status): void
    {
        $recipient = $status['recipient_id'] ?? null;
        $state     = $status['status']       ?? null;
        $errors    = $status['errors']       ?? [];

        if ($state === 'failed' && $recipient) {
            $codes = array_filter(array_map(fn($e) => is_array($e) ? ($e['code'] ?? null) : null, $errors));
            Log::warning('WhatsApp delivery failed', ['recipient' => $recipient, 'codes' => array_values($codes)]);

            if (in_array(131026, array_values($codes), true)) {
                Subscriber::where('phone_number', $recipient)->update(['window_failed' => true]);
            }
        }
    }
}
