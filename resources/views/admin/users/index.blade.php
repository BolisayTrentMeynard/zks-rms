@php
    $input = 'mt-1.5 block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-base focus:border-pool focus:outline-none focus:ring-2 focus:ring-sky/40';
@endphp

<x-layouts.app title="User List">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">User List</h1>
        <p class="mt-2 max-w-prose text-ink/70">
            Guest accounts. A deactivated guest cannot log in or submit reservations; their records stay.
        </p>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-lg border border-palm/30 bg-palm/10 px-4 py-3 text-sm font-medium text-palm">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="mt-6 rounded-lg border border-alert/30 bg-alert/10 px-4 py-3 text-sm font-medium text-alert">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('admin.users.index') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="q" class="block text-sm font-semibold">Search by name</label>
                <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" class="{{ $input }}">
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-pool px-5 py-2.5 text-base font-semibold text-white hover:bg-ink">Search</button>
                @if ($search !== '')
                    <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-pool hover:underline">Clear</a>
                @endif
            </div>
        </form>

        @if ($users->isEmpty())
            <p class="mt-8 rounded-xl border border-line bg-white p-6 text-ink/70">No guest accounts found.</p>
        @else
            <ul class="mt-6 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
                @foreach ($users as $user)
                    <li class="px-4 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $user->name }}</p>
                                <p class="text-sm text-ink/70">{{ $user->phone ?: '-' }}</p>
                                <p class="break-all text-sm text-ink/70">{{ $user->email }}</p>
                            </div>
                            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold {{ $user->is_active ? 'bg-palm/15 text-palm' : 'bg-slate-200 text-slate-700' }}">
                                {{ $user->is_active ? 'Active' : 'Deactivated' }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                            <a href="{{ route('admin.dashboard', ['guest' => $user->id]) }}" class="font-semibold text-pool hover:underline">View reservations</a>
                            <a href="{{ route('admin.activity-log', ['guest' => $user->id]) }}" class="font-semibold text-pool hover:underline">View activity log</a>

                            @if ($user->is_active)
                                <form method="POST" action="{{ route('admin.users.deactivate', ['user' => $user, 'q' => $search, 'page' => $users->currentPage()]) }}" class="sm:ml-auto"
                                      onsubmit="return confirm('Deactivate this account? They will not be able to log in or submit reservations.')">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-alert bg-white px-4 py-2 font-semibold text-alert hover:bg-alert/10">Deactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.users.reactivate', ['user' => $user, 'q' => $search, 'page' => $users->currentPage()]) }}" class="sm:ml-auto"
                                      onsubmit="return confirm('Reactivate this account? They will be able to log in again.')">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-palm px-4 py-2 font-semibold text-white hover:bg-ink">Reactivate</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $users->links() }}</div>
        @endif
    </div>
</x-layouts.app>
