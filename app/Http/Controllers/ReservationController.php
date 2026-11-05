<?php

namespace App\Http\Controllers;

use App\Enums\BookingType;
use App\Enums\Duration;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationController extends Controller
{
    /** The form, opened from a free date on the calendar: /reserve?date=YYYY-MM-DD */
    public function create(Request $request): View|RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403);

        $date = $this->bookableDate((string) $request->query('date', ''));

        if (! $date) {
            return redirect()->route('availability')->with('error', 'Please pick an open date from the calendar.');
        }

        if (ReservationService::isDateBlocked($date)) {
            return redirect()->route('availability', ['month' => $date->format('Y-m')])
                ->with('error', 'That date is no longer available. Please pick another one.');
        }

        return view('reservations.create', [
            'date' => $date,
            'earliest' => ReservationService::earliestDate(),
            'durations' => Duration::cases(),
            'types' => BookingType::cases(),
        ]);
    }

    public function store(StoreReservationRequest $request, ReservationService $service): RedirectResponse
    {
        $reservation = $service->hold($request->user(), $request->validated());

        if (! $reservation) {
            return redirect()->route('availability', ['month' => substr($request->input('reservation_date'), 0, 7)])
                ->with('error', 'That date was just taken. Please pick another date.');
        }

        return redirect()->route('reservations.show', $reservation)
            ->with('status', 'Your date is held for '.config('zk.hold_hours').' hours.');
    }

    /** A guest sees only their own reservation. */
    public function show(Request $request, Reservation $reservation): View
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        return view('reservations.show', ['reservation' => $reservation->load('user')]);
    }

    /** Parses a Y-m-d date and returns it only when it is on or after the earliest bookable date. */
    private function bookableDate(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        if ($date->format('Y-m-d') !== $value || $date->lt(ReservationService::earliestDate())) {
            return null;
        }

        return $date;
    }
}
