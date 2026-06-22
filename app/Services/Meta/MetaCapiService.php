<?php

namespace App\Services\Meta;

use App\Models\Subscriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends server-side conversion events to the Meta Conversions API (CAPI).
 *
 * Signups happen inside WhatsApp, not in the browser, so the Pixel alone
 * cannot observe them. This service reports the signup as a `Lead` event so
 * Meta can attribute it to the originating ad and optimise delivery.
 */
class MetaCapiService
{
    /**
     * Report a subscriber signup to Meta as a `Lead` event.
     * Idempotent: sets meta_lead_sent_at and skips if already reported.
     */
    public function reportSignup(Subscriber $subscriber): bool
    {
        $pixelId = config('services.meta.pixel_id');
        $token   = config('services.meta.capi_token');

        if (!$pixelId || !$token) {
            Log::info('Meta CAPI not configured, skipping signup event', [
                'phone' => $subscriber->phone_number,
            ]);
            return false;
        }

        if ($subscriber->meta_lead_sent_at) {
            return false; // already reported
        }

        // Stable event_id so browser-side Pixel Lead (if any) is deduplicated.
        $eventId = 'sub_' . $subscriber->id;

        $userData = array_filter([
            // Phone must be E.164 digits, hashed with SHA-256.
            'ph' => $subscriber->phone_number ? hash('sha256', $subscriber->phone_number) : null,
            'country' => $subscriber->country ? hash('sha256', strtolower($subscriber->country)) : null,
            'fbc' => $this->buildFbc($subscriber),
            'fbp' => $subscriber->fbp,
            'client_ip_address' => $subscriber->attribution_ip,
        ]);

        $payload = [
            'data' => [[
                'event_name' => 'Lead',
                'event_time' => now()->timestamp,
                'event_id' => $eventId,
                'action_source' => 'business_messaging',
                'messaging_channel' => 'whatsapp',
                'user_data' => $userData,
                'custom_data' => array_filter([
                    'lead_source' => 'whatsapp_signup',
                    'utm_source' => $subscriber->utm_source,
                    'utm_campaign' => $subscriber->utm_campaign,
                ]),
            ]],
        ];

        if ($code = config('services.meta.test_event_code')) {
            $payload['test_event_code'] = $code;
        }

        try {
            $url = rtrim(config('services.meta.graph_url'), '/') . "/{$pixelId}/events";
            $resp = Http::timeout(8)->post($url, array_merge($payload, [
                'access_token' => $token,
            ]));

            if ($resp->successful()) {
                $subscriber->forceFill(['meta_lead_sent_at' => now()])->save();
                Log::info('Meta CAPI Lead reported', [
                    'phone' => $subscriber->phone_number,
                    'events_received' => $resp->json('events_received'),
                ]);
                return true;
            }

            Log::warning('Meta CAPI Lead failed', [
                'phone' => $subscriber->phone_number,
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Meta CAPI Lead exception', [
                'phone' => $subscriber->phone_number,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Build Meta's click identifier (`fbc`) from a stored fbclid.
     * Format: fb.<subdomainIndex>.<creationTimeMs>.<fbclid>
     */
    private function buildFbc(Subscriber $subscriber): ?string
    {
        if (empty($subscriber->fbclid)) {
            return null;
        }

        $ts = ($subscriber->created_at ?? now())->getTimestampMs();

        return "fb.1.{$ts}.{$subscriber->fbclid}";
    }
}
