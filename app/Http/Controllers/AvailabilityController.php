<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    /**
     * Public month calendar.
     * Visitors and other guests only learn that a date is Reserved. The owner sees the status
     * badge on their own reservation, and the Admin sees every badge with the booker's name.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $earliest = ReservationService::earliestDate();
        $openFrom = $viewer?->isAdmin() ? today() : $earliest; // the Admin enters same-day walk-ins
        $currentMonth = today()->startOfMonth();
        $month = $this->requestedMonth($request, $currentMonth);

        $blocked = Reservation::with('user:id,name')
            ->whereDate('blocking_date', '>=', $month->toDateString())
            ->whereDate('blocking_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->get()
            ->keyBy(fn (Reservation $r) => $r->blocking_date->toDateString());

        $cells = array_fill(0, $month->dayOfWeek, null); // empty slots before the 1st (week starts Sunday)

        for ($date = $month->copy(); $date->month === $month->month; $date->addDay()) {
            $key = $date->toDateString();
            $reservation = $blocked->get($key);
            $href = null;

            if ($reservation) {
                if ($viewer?->isAdmin()) {
                    $kind = 'admin';
                } elseif ($viewer && $reservation->user_id === $viewer->id) {
                    $kind = 'own';
                    $href = route('reservations.show', $reservation);
                } else {
                    $kind = 'reserved';
                }
            } elseif ($date->lt($openFrom)) {
                $kind = 'closed';
            } else {
                $kind = 'open';
                $href = $viewer?->isAdmin()
                    ? route('walk-ins.create', ['date' => $key])
                    : route('reservations.create', ['date' => $key]);
            }

            $cells[] = ['date' => $date->copy(), 'kind' => $kind, 'href' => $href, 'reservation' => $reservation];
        }

        return view('availability', [
            'month' => $month,
            'cells' => $cells,
            'prev' => $month->gt($currentMonth) ? $month->copy()->subMonth()->format('Y-m') : null,
            'next' => $month->copy()->addMonth()->format('Y-m'),
            'earliest' => $earliest,
        ]);
    }

    /** ?month=YYYY-MM, falling back to the current month. Past months are not shown. */
    private function requestedMonth(Request $request, Carbon $currentMonth): Carbon
    {
        $value = (string) $request->query('month', '');

        if (! preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $value)) {
            return $currentMonth;
        }

        $month = Carbon::createFromFormat('!Y-m', $value);

        return $month->lt($currentMonth) ? $currentMonth : $month;
    }
}
