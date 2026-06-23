<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaAdsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:sync-meta-ads-data')]
#[Description('Sync Meta Ads performance data from Facebook API')]
class SyncMetaAdsData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MetaAdsService $metaAdsService): int
    {
        $this->info('Starting Meta Ads data sync...');

        try {
            // Check if Meta Ads is configured
            if (!$metaAdsService->isConfigured()) {
                $this->error('Meta Ads service is not configured. Please check your .env file.');
                return Command::FAILURE;
            }

            // Sync campaign insights for today
            $this->info('Fetching campaign insights for today...');
            $campaignInsights = $metaAdsService->getCampaignInsights('today');
            
            if (isset($campaignInsights['error'])) {
                $this->error('Failed to fetch campaign insights: ' . $campaignInsights['error']);
                return Command::FAILURE;
            }

            if (!empty($campaignInsights)) {
                $stored = $metaAdsService->storeCampaignInsights($campaignInsights);
                if ($stored) {
                    $this->info('✅ Stored ' . count($campaignInsights) . ' campaign insight records');
                } else {
                    $this->error('❌ Failed to store campaign insights');
                    return Command::FAILURE;
                }
            } else {
                $this->info('No campaign insights found for today');
            }

            // Calculate and log key metrics
            $metrics = $metaAdsService->calculateMetrics($campaignInsights);
            $this->info('📊 Today\'s Performance Summary:');
            $this->info('   • Total Spend: $' . number_format($metrics['total_spend'], 2));
            $this->info('   • Impressions: ' . number_format($metrics['total_impressions']));
            $this->info('   • Clicks: ' . number_format($metrics['total_clicks']));
            $this->info('   • Conversions: ' . $metrics['total_conversions']);
            $this->info('   • Avg CTR: ' . number_format($metrics['avg_ctr'], 2) . '%');
            $this->info('   • Avg CPC: $' . number_format($metrics['avg_cpc'], 2));
            $this->info('   • ROAS: ' . number_format($metrics['roas'], 2));

            // Log to system for monitoring
            Log::info('Meta Ads data sync completed', [
                'campaigns_synced' => count($campaignInsights),
                'total_spend' => $metrics['total_spend'],
                'total_clicks' => $metrics['total_clicks'],
                'total_conversions' => $metrics['total_conversions'],
                'roas' => $metrics['roas']
            ]);

            $this->info('✅ Meta Ads data sync completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Meta Ads sync failed: ' . $e->getMessage());
            Log::error('Meta Ads data sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
