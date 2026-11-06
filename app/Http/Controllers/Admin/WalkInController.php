<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingType;
use App\Enums\Duration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWalkInRequest;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalkInController extends Controller
{
    /** The form. /admin/walk-ins/create?date=YYYY-MM-DD preselects a date from the calendar. */
    public function create(Request $request): View
    {
        return view('admin.walk-ins.create', [
            'date' => $this->preselectedDate((string) $request->query('date', '')),
            'durations' => Duration::cases(),
            'types' => BookingType::cases(),
        ]);
    }

    public function store(StoreWalkInRequest $request, ReservationService $service): RedirectResponse
    {
        $reservation = $service->walkIn($request->user(), $request->validated());

        if (! $reservation) {
            return redirect()->route('walk-ins.create', ['date' => $request->input('reservation_date')])
                ->withInput()
                ->withErrors(['reservation_date' => 'That date is already taken. Pick another date from the calendar.']);
        }

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('status', 'Walk-in saved. The reservation is Confirmed.');
    }

    /** A valid Y-m-d date from today onward, otherwise today. */
    private function preselectedDate(string $value): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            if ($date->format('Y-m-d') === $value && $date->gte(today())) {
                return $date;
            }
        }

        return today();
    }
}
