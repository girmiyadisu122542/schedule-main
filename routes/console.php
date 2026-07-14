<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscription lifecycle: expire lapsed subscriptions/grants daily; flush
// metered-usage counters into usage_tracking every 10 minutes for billing.
Schedule::command('subscriptions:expire')->dailyAt('00:05');
Schedule::command('usage:flush')->everyTenMinutes();
