<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Queue worker via scheduler (tanpa perlu jalankan queue:work manual)
| Setiap menit proses job yang antri, max 55 detik, lalu berhenti.
| Di server cukup satu cron: * * * * * cd /path && php artisan schedule:run
|--------------------------------------------------------------------------
*/
Schedule::command('queue:work database --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping(2);
