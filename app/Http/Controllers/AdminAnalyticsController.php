<?php

namespace App\Http\Controllers;

use App\Models\LandingVisit;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // Simple shared-secret auth via ?key=...
        $expected = config('app.admin_key', env('ADMIN_KEY'));
        if (!$expected || $request->query('key') !== $expected) {
            abort(403, 'Forbidden');
        }

        $since = now()->subDays((int) $request->query('days', 30));

        // Funnel
        $visits = LandingVisit::where('event', 'view')->where('created_at', '>=', $since)->count();
        $clicks = LandingVisit::where('event', 'cta_click')->where('created_at', '>=', $since)->count();
        $subscribers = Subscriber::where('created_at', '>=', $since)->count();
        $active = Subscriber::where('is_active', true)->where('created_at', '>=', $since)->count();

        $paidCount = DB::table('mpesa_transactions')
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->count();
        $revenue = (float) DB::table('mpesa_transactions')
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->sum('amount');

        // By source
        $bySource = LandingVisit::where('created_at', '>=', $since)
            ->selectRaw('COALESCE(utm_source, "direct") as source, COUNT(*) as visits, SUM(event = "cta_click") as clicks')
            ->groupBy('source')
            ->orderByDesc('visits')
            ->get();

        $subsBySource = Subscriber::where('created_at', '>=', $since)
            ->selectRaw('COALESCE(utm_source, "direct") as source, COUNT(*) as subs')
            ->groupBy('source')
            ->get()->keyBy('source');

        // By country
        $byCountry = LandingVisit::where('created_at', '>=', $since)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as visits')
            ->groupBy('country')
            ->orderByDesc('visits')
            ->limit(10)
            ->get();

        // Daily visits last 14 days
        $daily = LandingVisit::where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as date, SUM(event = "view") as views, SUM(event = "cta_click") as clicks')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.analytics', compact(
            'visits', 'clicks', 'subscribers', 'active',
            'paidCount', 'revenue',
            'bySource', 'subsBySource', 'byCountry', 'daily', 'since'
        ));
    }
}
