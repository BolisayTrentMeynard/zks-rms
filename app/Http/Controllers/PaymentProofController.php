<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reservation\StoreProofRequest;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;

class PaymentProofController extends Controller
{
    public function store(StoreProofRequest $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $sent = $service->submitProof($reservation, $request->file('proof'), $request->user());

        if (! $sent) {
            return redirect()->route('reservations.show', $reservation)
                ->with('error', 'A payment proof cannot be sent for this reservation right now.');
        }

        return redirect()->route('reservations.show', $reservation)
            ->with('status', 'Payment proof sent. The resort will check it and confirm your reservation.');
    }
}
