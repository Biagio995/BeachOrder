<?php

use App\Jobs\SuspendPastDueSubscriptionsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('privacy:purge')->dailyAt('03:00');
Schedule::command('backup:run')->dailyAt('02:00');
Schedule::command('monitor:health --alert')->everyFiveMinutes();
Schedule::command('monitor:failed-jobs --alert')->everyMinute();
Schedule::command('pos:retry-syncs')->everyMinute();
