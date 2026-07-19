<?php

use App\Jobs\SendEventReminderJob;
use App\Jobs\SendPendingPaymentJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendEventReminderJob)->dailyAt('09:00');
Schedule::job(new SendPendingPaymentJob)->dailyAt('08:00');
