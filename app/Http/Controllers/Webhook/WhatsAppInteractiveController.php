<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
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
        // Log incoming webhook (like bookyangu)
        Log::info('WhatsApp webhook received', $request->all());
        
        // Extract value array from webhook structure
        $data = $request->all();
        $entry = $data['entry'] ?? [];
        $changes = $entry[0]['changes'] ?? [];
        $value = $changes[0]['value'] ?? [];
        
        // Process message through service layer
        $result = $this->whatsappService->handleIncomingMessage($value);
        
        return response()->json($result);
    }
}
