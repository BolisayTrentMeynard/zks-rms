<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function show(Reservation $reservation): View
    {
        return view('admin.reservations.show', [
            'reservation' => $reservation->load(['user', 'latestPaymentProof']),
        ]);
    }

    public function confirm(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        return $this->respond(
            $reservation,
            $service->confirmPayment($reservation, $request->user()),
            'Payment confirmed. The reservation is now Confirmed.',
        );
    }

    public function flag(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        return $this->respond(
            $reservation,
            $service->flagProof($reservation, $request->user()),
            'Proof flagged. The guest was asked to upload a clearer screenshot.',
        );
    }

    private function respond(Reservation $reservation, bool $done, string $message): RedirectResponse
    {
        $back = redirect()->route('admin.reservations.show', $reservation);

        return $done
            ? $back->with('status', $message)
            : $back->with('error', 'There is no payment proof waiting for review on this reservation.');
    }
}
