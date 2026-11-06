<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActorType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Guest accounts only. The Admin can search, deactivate and reactivate.
 * There is no delete, edit, create or password reset.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));

        $users = User::where('role', UserRole::Guest)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'search' => $search]);
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        return $this->setActive($request, $user, false);
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        return $this->setActive($request, $user, true);
    }

    private function setActive(Request $request, User $user, bool $active): RedirectResponse
    {
        abort_unless($user->isGuest(), 404); // the Admin account is never listed or changed here

        $back = redirect()->route('admin.users.index', $request->only('q', 'page'));

        if ($user->is_active === $active) {
            return $back->with('error', $user->name.' is already '.($active ? 'active' : 'deactivated').'.');
        }

        $user->update(['is_active' => $active]);

        ActivityLogger::account(
            $active ? 'reactivated' : 'deactivated',
            $user,
            $active ? 'Admin reactivated the account of '.$user->name : 'Admin deactivated the account of '.$user->name,
            ActorType::Admin,
            $request->user(),
        );

        return $back->with('status', $active
            ? $user->name.' was reactivated and can log in again.'
            : $user->name.' was deactivated. They can no longer log in or submit reservations.');
    }
}
