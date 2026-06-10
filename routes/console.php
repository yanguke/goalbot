<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll live matches every minute during tournament
Schedule::command('matches:poll')->everyMinute();

// Send reminders every 5 minutes (will only send when matches are ~2h away)
Schedule::command('reminders:send')->everyFiveMinutes();
