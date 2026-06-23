<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('test-scheduler')]
#[Description('Test scheduler functionality')]
class TestScheduler extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Log::info('Test scheduler command executed at: ' . now()->toDateTimeString());
        $this->info('Test scheduler executed successfully at: ' . now()->toDateTimeString());
        return Command::SUCCESS;
    }
}
