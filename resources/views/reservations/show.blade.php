@php
    use App\Enums\ReservationStatus as Status;

    $holding = $reservation->status === Status::SlotHeld && $reservation->hold_expires_at?->isFuture();
    $holdEnded = $reservation->status === Status::SlotHeld && ! $holding;
    $minutes = $holding ? (int) now()->diffInMinutes($reservation->hold_expires_at, false) : 0;
    $canUpload = $reservation->canUploadProof();
@endphp

<x-layouts.app title="Your reservation">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">Your reservation</h1>

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

        {{-- What happens next, by status --}}
        @switch($reservation->status)
            @case(Status::SlotHeld)
                @if ($holding)
                    <p class="mt-5 text-ink/70">
                        Send a screenshot of your GCash downpayment before the time runs out, or the date opens up again.
                    </p>
                @else
                    <p class="mt-5 text-ink/70">
                        The hold has ended, so this date is being released. You can pick another date from the calendar.
                    </p>
                @endif
                @break
            @case(Status::VerifyingPayment)
                <p class="mt-5 text-ink/70">We received your payment proof and the resort is checking it. The status here will change once it is confirmed.</p>
                @break
            @case(Status::ActionNeeded)
                <div role="alert" class="mt-5 rounded-lg border border-alert/30 bg-alert/10 px-4 py-3 text-sm text-alert">
                    <span class="font-semibold">We could not accept your payment proof.</span>
                    Please upload a clearer screenshot of your GCash payment. Your date is still held for you.
                </div>
                @break
            @case(Status::Confirmed)
                <p class="mt-5 font-medium text-palm">Your reservation is confirmed. See you at the resort.</p>
                @break
            @case(Status::Expired)
                <p class="mt-5 text-ink/70">This hold ended before a payment proof was sent, and the date was released. You can pick another date from the calendar.</p>
                @break
            @case(Status::Cancelled)
                <p class="mt-5 text-ink/70">This reservation was cancelled.</p>
                @break
        @endswitch

        @if ($canUpload)
            <form method="POST" action="{{ route('reservations.proof', $reservation) }}" enctype="multipart/form-data"
                  class="mt-5 rounded-xl border border-line bg-white p-5 sm:p-6" novalidate>
                @csrf
                <label for="proof" class="block text-sm font-semibold">GCash payment screenshot</label>
                <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required
                       aria-describedby="proof-note"
                       class="mt-1.5 block w-full rounded-lg border {{ $errors->has('proof') ? 'border-alert' : 'border-line' }} bg-white p-2.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-mist file:px-3 file:py-1.5 file:font-semibold">
                <p id="proof-note" class="mt-1.5 text-sm {{ $errors->has('proof') ? 'text-alert' : 'text-ink/60' }}">
                    {{ $errors->first('proof') ?: 'A JPG, PNG or WebP picture, up to '.round(config('zk.proof_max_kb') / 1024).' MB. Only the resort can see it.' }}
                </p>
                <button type="submit" class="mt-4 w-full rounded-lg bg-pool px-4 py-3 text-base font-semibold text-white hover:bg-ink">
                    Send payment proof
                </button>
            </form>
        @endif

        <a href="{{ route('availability') }}" class="mt-6 inline-block font-semibold text-pool hover:underline">Back to the calendar</a>
    </div>
</x-layouts.app>
