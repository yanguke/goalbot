<?php

namespace App\Http\Controllers;

use App\Models\LandingVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LandingPageController extends Controller
{
    public function index(Request $request)
    {
        $this->recordVisit($request, 'view');

        $version = $request->query('v', '1');

        $view = match($version) {
            '2' => 'welcome-v2-light',
            '3' => 'welcome-v3-bold',
            '4' => 'welcome-v4-minimal',
            default => 'welcome',
        };

        // Allow override via ?country=KE for testing
        $countryOverride = $request->query('country');
        $country = $countryOverride ? strtoupper($countryOverride) : $this->detectCountry($request);
        $isKenya = $country === 'KE';

        $pricing = $isKenya ? [
            'currency' => 'KES',
            'symbol' => 'KSh',
            'per_match' => 49,
            'per_match_label' => 'KES 49',
            'full' => 1999,
            'full_label' => 'KES 1,999',
            'is_kenya' => true,
        ] : [
            'currency' => 'USD',
            'symbol' => '$',
            'per_match' => 0.99,
            'per_match_label' => '$0.99',
            'full' => 9.99,
            'full_label' => '$9.99',
            'is_kenya' => false,
        ];

        Log::info('Landing page view', [
            'version' => $version,
            'ip' => $request->ip(),
            'country' => $country,
            'is_kenya' => $isKenya,
            'user_agent' => $request->userAgent(),
        ]);

        return view($view, compact('pricing', 'country', 'isKenya'));
    }

    /**
     * Tracked outbound click to WhatsApp.
     * Usage: /go?utm_source=tiktok&utm_campaign=launch
     */
    public function click(Request $request)
    {
        // Direct WhatsApp redirect without tracking
        $phone = config('services.whatsapp.phone_number', '254715333355');
        $msg   = 'Goal';
        $url   = "https://wa.me/{$phone}?text=" . urlencode($msg);

        return redirect()->away($url, 302);
    }

    /**
     * Persist a landing-page event (view or CTA click) with UTMs and geo.
     */
    private function recordVisit(Request $request, string $event): ?LandingVisit
    {
        try {
            $country = $this->detectCountry($request);

            return LandingVisit::create([
                'ip' => $request->ip(),
                'country' => $country !== 'XX' ? $country : null,
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'referrer' => substr((string) $request->headers->get('referer'), 0, 500) ?: null,
                'version' => (string) $request->query('v', '1'),
                'utm_source' => $request->query('utm_source'),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
                'utm_term' => $request->query('utm_term'),
                'utm_content' => $request->query('utm_content'),
                'fbclid' => $request->query('fbclid'),
                'fbp' => $request->cookie('_fbp'),
                'event' => $event,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Landing visit log failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Detect visitor country from IP. Uses Cloudflare header if present,
     * otherwise falls back to ip-api.com (free, no key, cached per IP).
     */
    private function detectCountry(Request $request): string
    {
        // Cloudflare provides this header automatically
        if ($cf = $request->header('CF-IPCountry')) {
            return strtoupper($cf);
        }

        $ip = $request->ip();
        if (in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($ip, '192.168.')) {
            return 'XX';
        }

        return Cache::remember("geo_country_{$ip}", 86400, function () use ($ip) {
            try {
                $resp = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,countryCode',
                ]);
                if ($resp->successful() && $resp->json('status') === 'success') {
                    return strtoupper($resp->json('countryCode', 'XX'));
                }
            } catch (\Exception $e) {
                Log::warning('Geo lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
            }
            return 'XX';
        });
    }
}
