<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Send hourly metrics report to monitoring group
        $schedule->command('app:send-hourly-metrics')
            ->hourly()
            ->description('Send hourly platform metrics to WhatsApp monitoring group')
            ->withoutOverlapping();

        // Send daily metrics report (optional - can be enabled if needed)
        // $schedule->command('app:send-daily-metrics')
        //     ->dailyAt('09:00')
        //     ->description('Send daily platform metrics summary');

        // Poll matches for live updates
        $schedule->command('app:poll-matches')
            ->everyMinute()
            ->description('Poll for live match updates')
            ->withoutOverlapping();

        // Send morning digest
        $schedule->command('app:send-morning-digest')
            ->dailyAt('08:00')
            ->description('Send morning match digest')
            ->withoutOverlapping();

        // Send match reminders
        $schedule->command('app:send-reminders')
            ->everyThirtyMinutes()
            ->description('Send match reminders')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
