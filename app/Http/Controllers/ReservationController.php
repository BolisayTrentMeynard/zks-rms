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

    /** My Reservations: the guest's own reservations, newest date first. */
    public function index(Request $request): View
    {
        abort_if($request->user()->isAdmin(), 403);

        $reservations = Reservation::where('user_id', $request->user()->id)
            ->orderByDesc('reservation_date')
            ->orderByDesc('id')
            ->paginate(10);

        return view('reservations.index', ['reservations' => $reservations]);
    }

    /** A guest sees only their own reservation. */
    public function show(Request $request, Reservation $reservation): View
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        return view('reservations.show', ['reservation' => $reservation->load('user')]);
    }

    /** A guest cancels their own Slot Held reservation (no money involved yet). */
    public function cancel(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        if (! $service->cancelHeld($reservation, $request->user())) {
            return redirect()->route('reservations.show', $reservation)
                ->with('error', 'This reservation can no longer be cancelled here.');
        }

        return redirect()->route('reservations.index')->with('status', 'Your reservation was cancelled and the date is open again.');
    }

    /** A guest asks to cancel (Verifying Payment, Action Needed or Confirmed). The Admin decides. */
    public function requestCancel(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        $error = $service->requestCancel($reservation, $request->user());

        return redirect()->route('reservations.show', $reservation)->with(
            $error ? 'error' : 'status',
            $error ?? 'Your cancellation request was sent to the resort. Your date stays reserved until it is approved.',
        );
    }

    /** A guest asks to move a Confirmed reservation to a free date. The Admin decides. */
    public function requestReschedule(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        $data = $request->validate(['new_date' => ['required', 'date_format:Y-m-d']], [
            'new_date.required' => 'Please pick the new date.',
            'new_date.date_format' => 'Please pick a valid date.',
        ]);

        $error = $service->requestReschedule($reservation, $request->user(), $data['new_date']);

        if ($error) {
            return redirect()->route('reservations.show', $reservation)->withErrors(['new_date' => $error])->withInput();
        }

        return redirect()->route('reservations.show', $reservation)
            ->with('status', 'Your reschedule request was sent to the resort. Your current date stays reserved until it is approved.');
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
