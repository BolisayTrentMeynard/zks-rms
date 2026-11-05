<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expire Slot Held reservations whose 24-hour hold has ended and reopen their dates.
// Locally this needs `php artisan schedule:work` running in its own terminal.
Schedule::command('reservations:expire-holds')->everyMinute()->withoutOverlapping();
