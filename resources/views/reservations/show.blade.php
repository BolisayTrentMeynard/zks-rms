@php
    $holding = $reservation->status === \App\Enums\ReservationStatus::SlotHeld
        && $reservation->hold_expires_at?->isFuture();
    $minutes = $holding ? (int) now()->diffInMinutes($reservation->hold_expires_at, false) : 0;
@endphp

<x-layouts.app title="Your reservation">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">Your reservation</h1>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-lg border border-palm/30 bg-palm/10 px-4 py-3 text-sm font-medium text-palm">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-6 rounded-xl border border-line bg-white p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-display text-xl font-bold">{{ $reservation->reservation_date->format('l, F j, Y') }}</p>
                <x-status-badge :status="$reservation->status" large />
            </div>

            @if ($holding)
                <div class="mt-5 rounded-lg bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">Time left to hold this date</p>
                    <p class="mt-0.5 font-display text-2xl font-bold text-amber-900" data-countdown="{{ $reservation->hold_expires_at->toIso8601String() }}">
                        {{ intdiv($minutes, 60) }}h {{ str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) }}m
                    </p>
                </div>
            @endif

            <dl class="mt-5 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                <dt class="text-ink/60">Name</dt><dd class="font-medium">{{ $reservation->bookerName() }}</dd>
                <dt class="text-ink/60">Contact</dt><dd class="font-medium">{{ $reservation->user?->phone ?? '-' }}</dd>
                <dt class="text-ink/60">Booking type</dt><dd class="font-medium">{{ $reservation->booking_type->label() }}</dd>
                <dt class="text-ink/60">Duration</dt><dd class="font-medium">{{ $reservation->duration->label() }}</dd>
                <dt class="text-ink/60">Guests</dt><dd class="font-medium">{{ $reservation->pax }}</dd>
                <dt class="text-ink/60">Occasion</dt><dd class="font-medium">{{ $reservation->occasion }}</dd>
            </dl>
        </div>

        @if ($holding)
            <p class="mt-5 text-ink/70">
                Next, send a screenshot of your GCash downpayment before the time runs out, or the date opens up again.
                The upload button is coming in the next part of the system.
            </p>
        @endif

        <a href="{{ route('availability') }}" class="mt-6 inline-block font-semibold text-pool hover:underline">Back to the calendar</a>
    </div>
</x-layouts.app>
