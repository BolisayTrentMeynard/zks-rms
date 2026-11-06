<x-layouts.app title="My Reservations">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">My Reservations</h1>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-lg border border-palm/30 bg-palm/10 px-4 py-3 text-sm font-medium text-palm">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div role="alert" class="mt-6 rounded-lg border border-alert/30 bg-alert/10 px-4 py-3 text-sm font-medium text-alert">
                {{ session('error') }}
            </div>
        @endif

        @forelse ($reservations as $reservation)
            @if ($loop->first)
                <ul class="mt-6 space-y-3">
            @endif
                <li>
                    <a href="{{ route('reservations.show', $reservation) }}"
                       class="block rounded-xl border border-line bg-white p-4 hover:border-pool sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-display text-lg font-bold">{{ $reservation->reservation_date->format('l, F j, Y') }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($reservation->change_request)
                                    <span class="inline-block rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900">{{ $reservation->change_request->label() }}</span>
                                @endif
                                <x-status-badge :status="$reservation->status" large />
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-ink/70">
                            {{ $reservation->booking_type->label() }} &middot; {{ $reservation->duration->label() }} &middot; {{ $reservation->pax }} guests
                        </p>
                    </a>
                </li>
            @if ($loop->last)
                </ul>
            @endif
        @empty
            <div class="mt-6 rounded-xl border border-line bg-white p-6 text-center">
                <p class="text-ink/70">You have no reservations yet.</p>
                <a href="{{ route('availability') }}" class="mt-3 inline-block rounded-lg bg-pool px-4 py-2 font-semibold text-white hover:bg-ink">See open dates</a>
            </div>
        @endforelse

        <div class="mt-6">{{ $reservations->links() }}</div>
    </div>
</x-layouts.app>
