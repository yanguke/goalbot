<?php

namespace App\Console\Commands;

use App\Services\Metrics\MetricsReportingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-hourly-metrics')]
#[Description('Send hourly platform metrics to WhatsApp monitoring group')]
class SendDailyMetrics extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MetricsReportingService $metricsService): int
    {
        $this->info('Sending hourly metrics report...');
        
        $success = $metricsService->sendHourlyReport();
        
        if ($success) {
            $this->info('✅ Hourly metrics report sent successfully!');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Failed to send hourly metrics report!');
            return Command::FAILURE;
        }
    }
}
