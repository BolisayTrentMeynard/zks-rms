<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireHolds extends Command
{
    protected $signature = 'reservations:expire-holds';

    protected $description = 'Expire Slot Held reservations whose 24-hour hold has ended and reopen their dates';

    public function handle(ReservationService $service): int
    {
        $count = $service->expireDueHolds();

        $this->info($count.' hold(s) expired.');

        return self::SUCCESS;
    }
}
