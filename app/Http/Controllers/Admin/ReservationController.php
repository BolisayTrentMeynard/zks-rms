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

    public function approve(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $error = $service->approveChange($reservation, $request->user());
        $back = redirect()->route('admin.reservations.show', $reservation);

        return $error ? $back->with('error', $error) : $back->with('status', 'Request approved.');
    }

    public function decline(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $back = redirect()->route('admin.reservations.show', $reservation);

        return $service->declineChange($reservation, $request->user())
            ? $back->with('status', 'Request declined. The reservation stays as it was.')
            : $back->with('error', 'There is no request waiting on this reservation.');
    }

    public function cancel(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $back = redirect()->route('admin.reservations.show', $reservation);

        return $service->adminCancel($reservation, $request->user(), $request->boolean('downpayment_forfeited'))
            ? $back->with('status', 'Reservation cancelled. The date is open again.')
            : $back->with('error', 'This reservation is already cancelled or expired.');
    }

    private function respond(Reservation $reservation, bool $done, string $message): RedirectResponse
    {
        $back = redirect()->route('admin.reservations.show', $reservation);

        return $done
            ? $back->with('status', $message)
            : $back->with('error', 'There is no payment proof waiting for review on this reservation.');
    }
}
