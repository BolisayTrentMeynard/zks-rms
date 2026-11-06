<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Reservation list with a Verifying Payment count first, a status filter and a guest-name search. */
    public function index(Request $request): View
    {
        // Bad values in the address bar are ignored rather than shown as errors.
        $status = ReservationStatus::tryFrom((string) $request->query('status', ''));
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $guestId = $request->query('guest');
        $guest = is_string($guestId) && ctype_digit($guestId) ? User::find((int) $guestId) : null; // from the User List link

        $reservations = Reservation::with('user')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($guest, fn ($query) => $query->where('user_id', $guest->id))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(fn ($q) => $q
                    ->where('guest_name', 'like', $like) // walk-ins
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)));
            })
            ->orderByDesc('reservation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.dashboard', [
            'reservations' => $reservations,
            'verifyingCount' => Reservation::where('status', ReservationStatus::VerifyingPayment)->count(),
            'statuses' => ReservationStatus::cases(),
            'status' => $status,
            'search' => $search,
            'guest' => $guest,
        ]);
    }
}
