<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
}
