@php
    use App\Enums\ProofStatus;
    use App\Enums\ChangeRequest;
    use App\Enums\ReservationStatus as Status;
    use App\Services\ReservationService;

    $proof = $reservation->latestPaymentProof;
    $canReview = $reservation->status === Status::VerifyingPayment && $proof?->status === ProofStatus::ForReview;
@endphp

<x-layouts.app title="Reservation">
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-pool hover:underline">Back to the dashboard</a>

        <h1 class="mt-3 font-display text-3xl font-bold">Reservation</h1>

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
                <div class="flex flex-wrap items-center gap-2">
                    @if ($reservation->change_request)
                        <span class="inline-block rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900">{{ $reservation->change_request->label() }}</span>
                    @endif
                    <x-status-badge :status="$reservation->status" large />
                </div>
            </div>

            <dl class="mt-5 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                <dt class="text-ink/60">Name</dt>
                <dd class="font-medium">{{ $reservation->bookerName() }}@if ($reservation->is_walk_in) <span class="text-ink/50">(walk-in)</span>@endif</dd>
                <dt class="text-ink/60">Contact</dt>
                <dd class="font-medium">{{ ($reservation->is_walk_in ? $reservation->guest_contact : $reservation->user?->phone) ?: '-' }}</dd>
                @if (! $reservation->is_walk_in)
                    <dt class="text-ink/60">Email</dt>
                    <dd class="break-all font-medium">{{ $reservation->user?->email ?? '-' }}</dd>
                @endif
                <dt class="text-ink/60">Booking type</dt><dd class="font-medium">{{ $reservation->booking_type->label() }}</dd>
                <dt class="text-ink/60">Duration</dt><dd class="font-medium">{{ $reservation->duration->label() }}</dd>
                <dt class="text-ink/60">Guests</dt><dd class="font-medium">{{ $reservation->pax }}</dd>
                <dt class="text-ink/60">Occasion</dt><dd class="font-medium">{{ $reservation->occasion }}</dd>
                <dt class="text-ink/60">Submitted</dt><dd class="font-medium">{{ $reservation->created_at->format('M j, Y, g:i A') }}</dd>
                @if ($reservation->status === Status::SlotHeld && $reservation->hold_expires_at)
                    <dt class="text-ink/60">Hold ends</dt><dd class="font-medium">{{ $reservation->hold_expires_at->format('M j, Y, g:i A') }}</dd>
                @endif
            </dl>
        </div>

        {{-- Pending reschedule / cancellation request --}}
        @if ($reservation->change_request)
            <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-5 sm:p-6">
                <h2 class="font-display text-xl font-bold text-amber-900">{{ $reservation->change_request->label() }}</h2>
                <p class="mt-1 text-sm text-amber-900">Sent {{ $reservation->change_requested_at?->format('M j, Y, g:i A') }}. The date stays blocked until you decide.</p>

                @if ($reservation->change_request === ChangeRequest::Reschedule && $reservation->requested_date)
                    <p class="mt-3 text-sm"><span class="text-ink/60">Requested new date:</span>
                        <span class="font-semibold">{{ $reservation->requested_date->format('l, F j, Y') }}</span></p>
                    @if (ReservationService::isDateBlocked($reservation->requested_date))
                        <p role="alert" class="mt-2 text-sm font-semibold text-alert">That date has been taken since the request. Approving will fail; you can decline.</p>
                    @endif
                @endif

                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <form method="POST" action="{{ route('admin.reservations.approve', $reservation) }}" class="sm:flex-1"
                          onsubmit="return confirm('Approve this request?')">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-palm px-4 py-3 text-base font-semibold text-white hover:bg-ink">Approve request</button>
                    </form>
                    <form method="POST" action="{{ route('admin.reservations.decline', $reservation) }}" class="sm:flex-1"
                          onsubmit="return confirm('Decline this request? The reservation stays as it is.')">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-alert bg-white px-4 py-3 text-base font-semibold text-alert hover:bg-alert/10">Decline request</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Payment proof --}}
        <div class="mt-5 rounded-xl border border-line bg-white p-5 sm:p-6">
            <h2 class="font-display text-xl font-bold">Payment proof</h2>

            @if ($proof)
                <p class="mt-1 text-sm text-ink/70">
                    @switch($proof->status)
                        @case(ProofStatus::ForReview) Waiting for your review. @break
                        @case(ProofStatus::Accepted) Accepted. @break
                        @case(ProofStatus::Flagged) Flagged. The guest was asked for a clearer screenshot. @break
                    @endswitch
                </p>

                <a href="{{ route('admin.proofs.show', $proof) }}" target="_blank" rel="noopener" class="mt-4 block">
                    <img src="{{ route('admin.proofs.show', $proof) }}" alt="GCash payment screenshot from {{ $reservation->bookerName() }}"
                         class="max-h-[32rem] w-full rounded-lg border border-line bg-mist object-contain">
                </a>
                <a href="{{ route('admin.proofs.show', $proof) }}" target="_blank" rel="noopener"
                   class="mt-2 inline-block text-sm font-semibold text-pool hover:underline">Open full size</a>
            @elseif ($reservation->is_walk_in)
                <p class="mt-1 text-sm text-ink/70">Walk-in entered by the Admin. No payment proof is needed here.</p>
            @else
                <p class="mt-1 text-sm text-ink/70">No payment proof has been sent yet.</p>
            @endif

            @if ($canReview)
                <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                    <form method="POST" action="{{ route('admin.reservations.confirm', $reservation) }}" class="sm:flex-1"
                          onsubmit="return confirm('Confirm this payment? The reservation will become Confirmed.')">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-palm px-4 py-3 text-base font-semibold text-white hover:bg-ink">Confirm payment</button>
                    </form>
                    <form method="POST" action="{{ route('admin.reservations.flag', $reservation) }}" class="sm:flex-1"
                          onsubmit="return confirm('Flag this proof? The guest will be asked to upload a clearer screenshot.')">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-alert bg-white px-4 py-3 text-base font-semibold text-alert hover:bg-alert/10">Flag proof</button>
                    </form>
                </div>
            @elseif ($reservation->status === Status::ActionNeeded)
                <p class="mt-4 text-sm text-ink/70">Waiting for the guest to upload a clearer screenshot. The date stays held for them.</p>
            @elseif ($reservation->status === Status::SlotHeld)
                <p class="mt-4 text-sm text-ink/70">Waiting for the guest to send their payment proof before the hold ends.</p>
            @endif
        </div>

        {{-- Admin cancel: any reservation that still holds its date, at any time --}}
        @if ($reservation->status->blocksDate())
            <div class="mt-5 rounded-xl border border-line bg-white p-5 sm:p-6">
                <h2 class="font-display text-xl font-bold">Cancel reservation</h2>
                <p class="mt-1 text-sm text-ink/70">The date opens up again right away.</p>
                <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" class="mt-4"
                      onsubmit="return confirm('Cancel this reservation? The date will open up for other guests.')">
                    @csrf
                    @if ($reservation->status !== Status::SlotHeld)
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="downpayment_forfeited" value="1" class="mt-0.5 h-4 w-4 rounded border-line">
                            <span>Mark the downpayment as forfeited (non-refundable)</span>
                        </label>
                    @endif
                    <button type="submit" class="mt-4 w-full rounded-lg border border-alert bg-white px-4 py-3 text-base font-semibold text-alert hover:bg-alert/10">Cancel reservation</button>
                </form>
            </div>
        @elseif ($reservation->status === Status::Cancelled && $reservation->downpayment_forfeited)
            <p class="mt-5 text-sm font-medium text-ink/70">Cancelled. The downpayment was marked as forfeited.</p>
        @endif
    </div>
</x-layouts.app>
