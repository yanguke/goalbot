<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppVerifyController extends Controller
{
    /**
     * Handle WhatsApp webhook verification from Meta
     */
    public function verify(Request $request): mixed
    {
        $mode      = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token     = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        // Laravel's ConvertEmptyStringsToNull can blank dotted keys — read raw
        $raw = $request->server('QUERY_STRING', '');
        parse_str($raw, $params);
        $mode      = $mode      ?? ($params['hub.mode']         ?? null);
        $token     = $token     ?? ($params['hub.verify_token'] ?? null);
        $challenge = $challenge ?? ($params['hub.challenge']    ?? null);

        $expectedToken = config('services.whatsapp.verify_token');

        Log::info('WhatsApp webhook verification attempt', [
            'mode' => $mode, 'token' => $token, 'expected' => $expectedToken,
        ]);

        if ($mode === 'subscribe' && $token === $expectedToken && $challenge) {
            Log::info('WhatsApp webhook verified successfully');
            return response((string) $challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode, 'token_match' => ($token === $expectedToken),
        ]);
        return response('Verification failed', 403);
    }
}
