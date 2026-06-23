<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Thin wrapper around the Meta Marketing API for reading and managing ads.
 *
 * Uses a non-expiring System User token scoped to your own ad account, so it
 * needs no App Review. Read methods are safe; write methods (pause/budget)
 * mutate live campaigns and should be called deliberately.
 */
class MetaAdsService
{
    private string $base;
    private ?string $token;
    private ?string $adAccount;

    public function __construct()
    {
        $this->base = rtrim(config('services.meta.graph_url'), '/');
        $this->token = config('services.meta.system_user_token');
        $this->adAccount = config('services.meta.ad_account_id'); // e.g. act_123
    }

    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->adAccount);
    }

    /**
     * List campaigns with status and lifetime spend caps.
     */
    public function campaigns(int $limit = 50): array
    {
        return $this->get("{$this->adAccount}/campaigns", [
            'fields' => 'name,status,objective,daily_budget,lifetime_budget,effective_status',
            'limit' => $limit,
        ]);
    }

    /**
     * Performance insights (spend, impressions, results) for the account or a
     * specific campaign over a preset date range.
     */
    public function insights(?string $campaignId = null, string $datePreset = 'last_7d'): array
    {
        $node = $campaignId ?: $this->adAccount;

        return $this->get("{$node}/insights", [
            'fields' => 'campaign_name,spend,impressions,clicks,cpc,ctr,actions,cost_per_action_type',
            'date_preset' => $datePreset,
            'level' => $campaignId ? 'campaign' : 'account',
        ]);
    }

    /**
     * Pause or activate a campaign. $status: 'PAUSED' | 'ACTIVE'.
     */
    public function setCampaignStatus(string $campaignId, string $status): bool
    {
        return $this->post($campaignId, ['status' => $status]);
    }

    /**
     * Update a campaign's daily budget. Amount is in the account's minor unit
     * (e.g. cents/USD; for KES the account minor unit applies).
     */
    public function setDailyBudget(string $campaignId, int $minorAmount): bool
    {
        return $this->post($campaignId, ['daily_budget' => $minorAmount]);
    }

    // ── HTTP helpers ─────────────────────────────────────────────────────

    private function get(string $path, array $params = []): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Meta Ads not configured'];
        }

        try {
            $resp = Http::timeout(10)->get("{$this->base}/{$path}", array_merge($params, [
                'access_token' => $this->token,
            ]));

            if ($resp->successful()) {
                return $resp->json('data') !== null ? $resp->json() : $resp->json();
            }

            Log::warning('Meta Ads GET failed', ['path' => $path, 'body' => $resp->body()]);
            return ['error' => $resp->json('error.message', 'request failed')];
        } catch (\Throwable $e) {
            Log::warning('Meta Ads GET exception', ['path' => $path, 'error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    private function post(string $path, array $params): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $resp = Http::timeout(10)->asForm()->post("{$this->base}/{$path}", array_merge($params, [
                'access_token' => $this->token,
            ]));

            if ($resp->successful()) {
                return true;
            }

            Log::warning('Meta Ads POST failed', ['path' => $path, 'body' => $resp->body()]);
        } catch (\Throwable $e) {
            Log::warning('Meta Ads POST exception', ['path' => $path, 'error' => $e->getMessage()]);
        }

        return false;
    }

    // ── Performance Tracking Methods ─────────────────────────────────────

    /**
     * Fetch comprehensive campaign insights with breakdowns
     */
    public function getCampaignInsights(string $dateRange = 'today'): array
    {
        $fields = [
            'campaign_name',
            'campaign_id',
            'spend',
            'impressions',
            'clicks',
            'ctr',
            'cpc',
            'cpm',
            'actions',
            'action_values',
            'cost_per_action_type',
            'frequency',
            'reach'
        ];

        return $this->get("{$this->adAccount}/insights", [
            'fields' => implode(',', $fields),
            'level' => 'campaign',
            'date_preset' => $dateRange,
            'breakdowns' => 'platform,device_platform',
            'time_increment' => 1
        ]);
    }

    /**
     * Fetch ad set performance with demographic breakdowns
     */
    public function getAdSetInsights(string $dateRange = 'today'): array
    {
        $fields = [
            'campaign_name',
            'adset_name',
            'adset_id',
            'spend',
            'impressions',
            'clicks',
            'ctr',
            'cpc',
            'actions',
            'action_values',
            'cost_per_action_type',
            'frequency',
            'reach'
        ];

        return $this->get("{$this->adAccount}/insights", [
            'fields' => implode(',', $fields),
            'level' => 'adset',
            'date_preset' => $dateRange,
            'breakdowns' => 'age,gender,platform',
            'time_increment' => 1
        ]);
    }

    /**
     * Fetch ad creative performance data
     */
    public function getAdInsights(string $dateRange = 'today'): array
    {
        $fields = [
            'campaign_name',
            'adset_name',
            'ad_name',
            'ad_id',
            'ad_creative_id',
            'spend',
            'impressions',
            'clicks',
            'ctr',
            'cpc',
            'actions',
            'action_values',
            'cost_per_action_type'
        ];

        return $this->get("{$this->adAccount}/insights", [
            'fields' => implode(',', $fields),
            'level' => 'ad',
            'date_preset' => $dateRange,
            'time_increment' => 1
        ]);
    }

    /**
     * Fetch conversion data with attribution windows
     */
    public function getConversionData(string $dateRange = 'today'): array
    {
        $fields = [
            'campaign_name',
            'adset_name',
            'ad_name',
            'action_type',
            'action_values',
            'cost_per_action_type',
            'attribution_windows'
        ];

        return $this->get("{$this->adAccount}/insights", [
            'fields' => implode(',', $fields),
            'level' => 'campaign',
            'date_preset' => $dateRange,
            'attribution_windows' => 'click_7d,view_7d',
            'action_breakdowns' => 'action_type',
            'time_increment' => 1
        ]);
    }

    /**
     * Calculate key performance metrics from insights data
     */
    public function calculateMetrics(array $insights): array
    {
        $metrics = [
            'total_spend' => 0,
            'total_impressions' => 0,
            'total_clicks' => 0,
            'total_conversions' => 0,
            'total_conversion_value' => 0,
            'avg_ctr' => 0,
            'avg_cpc' => 0,
            'avg_cpm' => 0,
            'avg_cpa' => 0,
            'roas' => 0,
            'campaign_count' => count($insights)
        ];

        if (empty($insights)) {
            return $metrics;
        }

        foreach ($insights as $insight) {
            $metrics['total_spend'] += floatval($insight['spend'] ?? 0);
            $metrics['total_impressions'] += intval($insight['impressions'] ?? 0);
            $metrics['total_clicks'] += intval($insight['clicks'] ?? 0);
            
            // Extract conversions from actions
            if (isset($insight['actions'])) {
                foreach ($insight['actions'] as $action) {
                    if (in_array($action['action_type'], ['offsite_conversion.fb_pixel_purchase', 'offsite_conversion.fb_pixel_lead'])) {
                        $metrics['total_conversions'] += intval($action['value'] ?? 0);
                    }
                }
            }

            // Extract conversion values
            if (isset($insight['action_values'])) {
                foreach ($insight['action_values'] as $actionValue) {
                    if (in_array($actionValue['action_type'], ['offsite_conversion.fb_pixel_purchase', 'offsite_conversion.fb_pixel_lead'])) {
                        $metrics['total_conversion_value'] += floatval($actionValue['value'] ?? 0);
                    }
                }
            }
        }

        // Calculate averages and ratios
        $metrics['avg_ctr'] = $metrics['total_impressions'] > 0 
            ? ($metrics['total_clicks'] / $metrics['total_impressions']) * 100 
            : 0;
            
        $metrics['avg_cpc'] = $metrics['total_clicks'] > 0 
            ? $metrics['total_spend'] / $metrics['total_clicks'] 
            : 0;
            
        $metrics['avg_cpm'] = $metrics['total_impressions'] > 0 
            ? ($metrics['total_spend'] / $metrics['total_impressions']) * 1000 
            : 0;
            
        $metrics['avg_cpa'] = $metrics['total_conversions'] > 0 
            ? $metrics['total_spend'] / $metrics['total_conversions'] 
            : 0;
            
        $metrics['roas'] = $metrics['total_spend'] > 0 
            ? $metrics['total_conversion_value'] / $metrics['total_spend'] 
            : 0;

        return $metrics;
    }

    /**
     * Store campaign insights in database
     */
    public function storeCampaignInsights(array $insights): bool
    {
        try {
            foreach ($insights as $data) {
                DB::table('meta_campaign_insights')->updateOrInsert(
                    [
                        'campaign_id' => $data['campaign_id'] ?? null,
                        'date_start' => $data['date_start'] ?? null,
                        'platform' => $data['platform'] ?? null,
                        'device_platform' => $data['device_platform'] ?? null
                    ],
                    [
                        'campaign_name' => $data['campaign_name'] ?? '',
                        'date_stop' => $data['date_stop'] ?? null,
                        'spend' => floatval($data['spend'] ?? 0),
                        'impressions' => intval($data['impressions'] ?? 0),
                        'clicks' => intval($data['clicks'] ?? 0),
                        'ctr' => floatval($data['ctr'] ?? 0),
                        'cpc' => floatval($data['cpc'] ?? 0),
                        'cpm' => floatval($data['cpm'] ?? 0),
                        'frequency' => floatval($data['frequency'] ?? 0),
                        'reach' => intval($data['reach'] ?? 0),
                        'actions' => json_encode($data['actions'] ?? []),
                        'action_values' => json_encode($data['action_values'] ?? []),
                        'cost_per_action_type' => json_encode($data['cost_per_action_type'] ?? []),
                        'updated_at' => now()
                    ]
                );
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store campaign insights', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get performance trends for specific date range
     */
    public function getPerformanceTrends(string $dateRange = 'last_7d'): array
    {
        $insights = $this->insights(null, $dateRange);
        
        if (isset($insights['error'])) {
            return [];
        }

        $trends = [];
        foreach ($insights as $insight) {
            $date = $insight['date_start'] ?? date('Y-m-d');
            $trends[$date] = [
                'spend' => floatval($insight['spend'] ?? 0),
                'impressions' => intval($insight['impressions'] ?? 0),
                'clicks' => intval($insight['clicks'] ?? 0),
                'ctr' => floatval($insight['ctr'] ?? 0),
                'cpc' => floatval($insight['cpc'] ?? 0)
            ];
        }

        return $trends;
    }

    /**
     * Get top performing campaigns by ROAS
     */
    public function getTopCampaignsByROAS(string $dateRange = 'last_7d', int $limit = 10): array
    {
        $campaigns = $this->campaigns();
        $campaignPerformance = [];

        foreach ($campaigns as $campaign) {
            $insights = $this->insights($campaign['id'], $dateRange);
            
            if (!isset($insights['error']) && !empty($insights)) {
                $metrics = $this->calculateMetrics($insights);
                $campaignPerformance[] = [
                    'campaign_id' => $campaign['id'],
                    'campaign_name' => $campaign['name'],
                    'status' => $campaign['status'],
                    'objective' => $campaign['objective'],
                    'roas' => $metrics['roas'],
                    'spend' => $metrics['total_spend'],
                    'conversions' => $metrics['total_conversions'],
                    'ctr' => $metrics['avg_ctr'],
                    'cpc' => $metrics['avg_cpc']
                ];
            }
        }

        // Sort by ROAS descending
        usort($campaignPerformance, function($a, $b) {
            return $b['roas'] <=> $a['roas'];
        });

        return array_slice($campaignPerformance, 0, $limit);
    }

    /**
     * Get demographic performance breakdown
     */
    public function getDemographicBreakdown(string $dateRange = 'last_7d'): array
    {
        $adSetInsights = $this->getAdSetInsights($dateRange);
        
        if (isset($adSetInsights['error'])) {
            return [];
        }

        $demographics = [];
        foreach ($adSetInsights as $insight) {
            $age = $insight['age'] ?? 'unknown';
            $gender = $insight['gender'] ?? 'unknown';
            $key = "{$age}_{$gender}";

            if (!isset($demographics[$key])) {
                $demographics[$key] = [
                    'age' => $age,
                    'gender' => $gender,
                    'spend' => 0,
                    'impressions' => 0,
                    'clicks' => 0,
                    'conversions' => 0
                ];
            }

            $demographics[$key]['spend'] += floatval($insight['spend'] ?? 0);
            $demographics[$key]['impressions'] += intval($insight['impressions'] ?? 0);
            $demographics[$key]['clicks'] += intval($insight['clicks'] ?? 0);

            // Extract conversions
            if (isset($insight['actions'])) {
                foreach ($insight['actions'] as $action) {
                    if (in_array($action['action_type'], ['offsite_conversion.fb_pixel_purchase', 'offsite_conversion.fb_pixel_lead'])) {
                        $demographics[$key]['conversions'] += intval($action['value'] ?? 0);
                    }
                }
            }
        }

        return array_values($demographics);
    }
}
