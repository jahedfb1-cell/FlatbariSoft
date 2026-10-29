<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The office's monthly housekeeping, every morning (Dhaka time). On the shared host this needs one cron line:
//   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
\Illuminate\Support\Facades\Schedule::command('bms:daily')->dailyAt('09:00')->timezone('Asia/Dhaka')->withoutOverlapping();
