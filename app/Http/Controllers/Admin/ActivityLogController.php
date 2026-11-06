<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LogCategory;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Read-only Activity Log. There are no routes to change or delete an entry. */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        // Bad values in the address bar are ignored rather than shown as errors.
        $category = LogCategory::tryFrom((string) $request->query('type', ''));
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $from = $this->date((string) $request->query('from', ''));
        $to = $this->date((string) $request->query('to', ''));
        $reservation = $this->idOf($request->query('reservation'), Reservation::class);
        $guest = $this->idOf($request->query('guest'), User::class);

        $logs = ActivityLog::with(['user:id,name', 'reservation.user:id,name'])
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($reservation, fn ($q) => $q->where('reservation_id', $reservation->id))
            ->when($guest, fn ($q) => $q->where('user_id', $guest->id))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';

                $q->where(fn ($w) => $w
                    ->whereHas('user', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('reservation', fn ($r) => $r->where('guest_name', 'like', $like))); // walk-ins
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity-log', [
            'logs' => $logs,
            'category' => $category,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'reservation' => $reservation,
            'guest' => $guest,
            'categories' => LogCategory::cases(),
        ]);
    }

    /** A valid Y-m-d date, otherwise null. */
    private function date(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    /** The record for a numeric id, otherwise null (a bad or unknown id shows the unfiltered log). */
    private function idOf(mixed $value, string $model): ?object
    {
        return is_string($value) && ctype_digit($value) ? $model::find((int) $value) : null;
    }
}
