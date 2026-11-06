<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class SendHoldReminders extends Command
{
    protected $signature = 'reservations:send-hold-reminders';

    protected $description = 'Send the "hold expires in 2 hours" nudge once for each Slot Held reservation';

    public function handle(ReservationService $service): int
    {
        $count = $service->sendHoldReminders();

        $this->info($count.' reminder(s) sent.');

        return self::SUCCESS;
    }
}
