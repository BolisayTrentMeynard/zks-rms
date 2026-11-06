<x-layouts.app title="Dashboard">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-3xl font-bold">Dashboard</h1>
            <a href="{{ route('walk-ins.create') }}" class="rounded-lg bg-pool px-4 py-2.5 text-base font-semibold text-white hover:bg-ink">Add walk-in</a>
        </div>

        {{-- What needs the Admin first --}}
        <a href="{{ route('admin.dashboard', ['status' => 'verifying_payment']) }}"
           class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-line bg-white p-5 hover:border-pool">
            <div>
                <p class="text-sm font-semibold text-ink/60">Verifying Payment</p>
                <p class="mt-0.5 text-sm text-ink/70">
                    {{ $verifyingCount > 0 ? 'Payment proofs waiting for you to check.' : 'No payment proofs waiting right now.' }}
                </p>
            </div>
            <p class="font-display text-4xl font-bold {{ $verifyingCount > 0 ? 'text-pool' : 'text-ink/30' }}">{{ $verifyingCount }}</p>
        </a>

        @if ($guest)
            <p class="mt-6 rounded-lg bg-mist px-4 py-3 text-sm">
                Showing one guest: <span class="font-semibold">{{ $guest->name }}</span>.
                <a href="{{ route('admin.dashboard', request()->except(['guest', 'page'])) }}" class="font-semibold text-pool hover:underline">Clear</a>
            </p>
        @endif

        {{-- Filter and search --}}
        <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
            @if ($guest)<input type="hidden" name="guest" value="{{ $guest->id }}">@endif
            <div class="flex-1">
                <label for="q" class="block text-sm font-semibold">Search by guest name</label>
                <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100"
                       class="mt-1.5 block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-base focus:border-pool focus:outline-none focus:ring-2 focus:ring-sky/40">
            </div>
            <div class="sm:w-56">
                <label for="status" class="block text-sm font-semibold">Status</label>
                <select id="status" name="status"
                        class="mt-1.5 block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-base focus:border-pool focus:outline-none focus:ring-2 focus:ring-sky/40">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-pool px-5 py-2.5 text-base font-semibold text-white hover:bg-ink">Search</button>
                @if ($status || $search !== '' || $guest)
                    <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-pool hover:underline">Clear</a>
                @endif
            </div>
        </form>

        {{-- Reservation list --}}
        @if ($reservations->isEmpty())
            <p class="mt-8 rounded-xl border border-line bg-white p-6 text-ink/70">No reservations found.</p>
        @else
            <ul class="mt-6 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
                @foreach ($reservations as $reservation)
                    <li>
                        <a href="{{ route('admin.reservations.show', $reservation) }}"
                           class="flex flex-col gap-2 px-4 py-3.5 hover:bg-mist sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $reservation->reservation_date->format('D, M j, Y') }}</p>
                                <p class="truncate text-sm text-ink/70">
                                    {{ $reservation->bookerName() }}@if ($reservation->is_walk_in) <span class="text-ink/50">(walk-in)</span>@endif
                                </p>
                                <p class="text-sm text-ink/60">
                                    {{ $reservation->booking_type->label() }} · {{ $reservation->duration->label() }} · {{ $reservation->pax }} guests
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                @if ($reservation->change_request)
                                    <span class="inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold leading-tight text-amber-900 sm:text-xs">{{ $reservation->change_request->label() }}</span>
                                @endif
                                <x-status-badge :status="$reservation->status" />
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $reservations->links() }}</div>
        @endif
    </div>
</x-layouts.app>
