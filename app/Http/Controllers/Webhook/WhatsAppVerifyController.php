<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\MessageSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppVerifyController extends Controller
{
    /**
     * Handle WhatsApp webhook verification from Meta
     */
    public function verify(Request $request, MessageSender $sender): mixed
    {
        $mode = $request->input('hub.mode');
        $token = $request->input('hub.verify_token');
        $challenge = $request->input('hub.challenge');
        
        Log::info('WhatsApp webhook verification attempt', [
            'mode' => $mode,
            'token' => $token,
        ]);
        
        // Verify the subscription
        $response = $sender->verifyWebhook($token, $challenge);
        
        if ($response !== null) {
            Log::info('WhatsApp webhook verified successfully');
            return response($response, 200);
        }
        
        Log::warning('WhatsApp webhook verification failed');
        return response('Verification failed', 403);
    }
}
