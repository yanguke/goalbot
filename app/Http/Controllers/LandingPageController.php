<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LandingPageController extends Controller
{
    public function index(Request $request)
    {
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
            'full' => 999,
            'full_label' => 'KES 999',
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
