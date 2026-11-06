<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The bell: each account sees only its own notifications. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->appNotifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('notifications.index', [
            'notifications' => $notifications,
            'unread' => $request->user()->unreadNotificationCount(),
        ]);
    }

    /** Mark one as read and open the reservation it is about. */
    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->isUnread()) {
            $notification->update(['read_at' => now()]);
        }

        if (! $notification->reservation_id) {
            return redirect()->route('notifications.index');
        }

        return redirect()->route(
            $request->user()->isAdmin() ? 'admin.reservations.show' : 'reservations.show',
            $notification->reservation_id,
        );
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('notifications.index');
    }
}
