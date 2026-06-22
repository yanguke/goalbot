<?php

namespace App\Http\Controllers;

use App\Models\LandingVisit;
use App\Models\Subscriber;
use App\Services\Meta\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // Access is enforced by the 'admin' middleware (session login OR ?key=).
        // This inline guard remains as defense-in-depth.
        if (! \Illuminate\Support\Facades\Auth::check()) {
            $expected = config('app.admin_key', env('ADMIN_KEY'));
            if (!$expected || $request->query('key') !== $expected) {
                abort(403, 'Forbidden');
            }
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

        // Meta Ads insights (account-level, last 7 days). Gracefully degrades
        // to a "not configured" state when the System User token is absent.
        $ads = $this->metaAdsInsights();

        return view('admin.analytics', compact(
            'visits', 'clicks', 'subscribers', 'active',
            'paidCount', 'revenue',
            'bySource', 'subsBySource', 'byCountry', 'daily', 'since',
            'ads'
        ));
    }

    /**
     * Fetch and normalise Meta Ads campaign performance for the dashboard.
     */
    private function metaAdsInsights(): array
    {
        $service = app(MetaAdsService::class);

        if (! $service->isConfigured()) {
            return ['configured' => false, 'campaigns' => [], 'totals' => null];
        }

        $resp = $service->insights(null, 'last_7d');
        $rows = $resp['data'] ?? [];

        $campaigns = [];
        $totSpend = $totImpr = $totClicks = $totLeads = 0.0;

        foreach ($rows as $row) {
            $spend  = (float) ($row['spend'] ?? 0);
            $impr   = (int) ($row['impressions'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $leads  = $this->extractLeads($row['actions'] ?? []);

            $totSpend  += $spend;
            $totImpr   += $impr;
            $totClicks += $clicks;
            $totLeads  += $leads;

            $campaigns[] = [
                'name'          => $row['campaign_name'] ?? 'Account',
                'spend'         => $spend,
                'impressions'   => $impr,
                'clicks'        => $clicks,
                'ctr'           => (float) ($row['ctr'] ?? 0),
                'cpc'           => (float) ($row['cpc'] ?? 0),
                'leads'         => $leads,
                'cost_per_lead' => $leads > 0 ? $spend / $leads : null,
            ];
        }

        return [
            'configured' => true,
            'error'      => $resp['error'] ?? null,
            'campaigns'  => $campaigns,
            'totals'     => [
                'spend'         => $totSpend,
                'impressions'   => $totImpr,
                'clicks'        => $totClicks,
                'leads'         => $totLeads,
                'cost_per_lead' => $totLeads > 0 ? $totSpend / $totLeads : null,
            ],
        ];
    }

    /**
     * Sum lead/registration conversions from a Meta insights `actions` array.
     */
    private function extractLeads(array $actions): float
    {
        $leadTypes = ['lead', 'onsite_conversion.lead_grouped', 'offsite_conversion.fb_pixel_lead', 'complete_registration'];
        $total = 0.0;

        foreach ($actions as $action) {
            if (in_array($action['action_type'] ?? '', $leadTypes, true)) {
                $total += (float) ($action['value'] ?? 0);
            }
        }

        return $total;
    }
}
