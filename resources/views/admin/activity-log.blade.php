@php
    $input = 'mt-1.5 block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-base focus:border-pool focus:outline-none focus:ring-2 focus:ring-sky/40';
    $filtered = $category || $search !== '' || $from || $to || $reservation || $guest;
    $actorClasses = [
        'guest' => 'bg-sky/15 text-pool',
        'admin' => 'bg-palm/15 text-palm',
        'system' => 'bg-slate-200 text-slate-700',
    ];
@endphp

<x-layouts.app title="Activity Log">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">Activity Log</h1>
        <p class="mt-2 max-w-prose text-ink/70">
            A read-only record of reservation and account events. Entries cannot be edited or deleted.
        </p>

        @if ($reservation)
            <p class="mt-5 rounded-lg bg-mist px-4 py-3 text-sm">
                Showing one reservation:
                <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-semibold text-pool hover:underline">{{ $reservation->bookerName() }}, {{ $reservation->reservation_date->format('M j, Y') }}</a>.
                <a href="{{ route('admin.activity-log', request()->except(['reservation', 'page'])) }}" class="font-semibold text-pool hover:underline">Clear</a>
            </p>
        @endif

        @if ($guest)
            <p class="mt-5 rounded-lg bg-mist px-4 py-3 text-sm">
                Showing one guest: <span class="font-semibold">{{ $guest->name }}</span>.
                <a href="{{ route('admin.activity-log', request()->except(['guest', 'page'])) }}" class="font-semibold text-pool hover:underline">Clear</a>
            </p>
        @endif

        <form method="GET" action="{{ route('admin.activity-log') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
            @if ($reservation)<input type="hidden" name="reservation" value="{{ $reservation->id }}">@endif
            @if ($guest)<input type="hidden" name="guest" value="{{ $guest->id }}">@endif

            <div class="sm:col-span-2">
                <label for="q" class="block text-sm font-semibold">Search by guest name</label>
                <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" class="{{ $input }}">
            </div>
            <div>
                <label for="type" class="block text-sm font-semibold">Event type</label>
                <select id="type" name="type" class="{{ $input }}">
                    <option value="">All events</option>
                    @foreach ($categories as $option)
                        <option value="{{ $option->value }}" @selected($category === $option)>{{ ucfirst($option->value) }} events</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="from" class="block text-sm font-semibold">From</label>
                    <input id="from" name="from" type="date" value="{{ $from }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="to" class="block text-sm font-semibold">To</label>
                    <input id="to" name="to" type="date" value="{{ $to }}" class="{{ $input }}">
                </div>
            </div>
            <div class="flex items-center gap-3 sm:col-span-2">
                <button type="submit" class="rounded-lg bg-pool px-5 py-2.5 text-base font-semibold text-white hover:bg-ink">Filter</button>
                @if ($filtered)
                    <a href="{{ route('admin.activity-log') }}" class="text-sm font-semibold text-pool hover:underline">Clear all</a>
                @endif
            </div>
        </form>

        @if ($logs->isEmpty())
            <p class="mt-8 rounded-xl border border-line bg-white p-6 text-ink/70">No activity found.</p>
        @else
            <ul class="mt-6 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
                @foreach ($logs as $log)
                    <li class="px-4 py-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold">{{ $log->eventLabel() }}</p>
                            <div class="flex items-center gap-2">
                                <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold leading-tight sm:text-xs {{ $actorClasses[$log->actor_type->value] }}">{{ ucfirst($log->actor_type->value) }}</span>
                                <time datetime="{{ $log->created_at->toIso8601String() }}" class="text-sm text-ink/60">{{ $log->created_at->format('M j, Y, g:i A') }}</time>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-ink/80">{{ $log->description }}</p>
                        <p class="mt-1 text-sm text-ink/60">
                            @if ($log->reservation)
                                <a href="{{ route('admin.reservations.show', $log->reservation) }}" class="font-semibold text-pool hover:underline">{{ $log->reservation->bookerName() }}, {{ $log->reservation->reservation_date->format('M j, Y') }}</a>
                            @elseif ($log->user)
                                {{ $log->user->name }}
                            @else
                                -
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $logs->links() }}</div>
        @endif
    </div>
</x-layouts.app>
