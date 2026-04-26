<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('admin:sla-escalations')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('admin:escalation-ladders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('admin:comms-retries')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

/*
| Production: cron `* * * * * cd /path/to/app && php artisan schedule:run`
| Then use `Illuminate\Support\Facades\Schedule` in this file (see Laravel docs).
*/
