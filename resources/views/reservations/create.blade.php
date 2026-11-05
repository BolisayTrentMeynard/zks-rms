@php
    $radio = 'flex cursor-pointer items-start gap-3 rounded-lg border border-line bg-white p-3 has-[:checked]:border-pool has-[:checked]:ring-2 has-[:checked]:ring-sky/30';
    $hints = [
        'pool' => 'The outdoor pool',
        'transient' => 'Transient stay',
        'room' => 'Living Area/Sala and Private CR',
        'venue_hall' => 'The venue hall',
    ];
@endphp

<x-layouts.app title="Reserve a date">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        <a href="{{ route('availability', ['month' => $date->format('Y-m')]) }}" class="text-sm font-semibold text-pool hover:underline">Back to the calendar</a>

        <h1 class="mt-3 font-display text-3xl font-bold">Reserve {{ $date->format('F j, Y') }}</h1>
        <p class="mt-2 text-ink/70">
            We hold your date for {{ config('zk.hold_hours') }} hours after you submit. Booking as
            <span class="font-semibold text-ink">{{ auth()->user()->name }}</span>
            ({{ auth()->user()->phone ?? 'no contact number' }}).
            <a href="{{ route('profile.edit') }}" class="font-semibold text-pool hover:underline">Edit your details</a>
        </p>

        <form method="POST" action="{{ route('reservations.store') }}" class="mt-7 space-y-6 rounded-xl border border-line bg-white p-5 sm:p-6" novalidate>
            @csrf

            <div>
                <label for="reservation_date" class="block text-sm font-semibold">Date</label>
                <input id="reservation_date" name="reservation_date" type="date" required
                       min="{{ $earliest->toDateString() }}"
                       value="{{ old('reservation_date', $date->toDateString()) }}"
                       @class([
                           'mt-1.5 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-base focus:border-pool focus:outline-none focus:ring-2 focus:ring-sky/40',
                           'border-line' => ! $errors->has('reservation_date'),
                           'border-alert' => $errors->has('reservation_date'),
                       ])>
                <p class="mt-1.5 text-sm {{ $errors->has('reservation_date') ? 'text-alert' : 'text-ink/60' }}">
                    {{ $errors->first('reservation_date') ?: 'If this date is taken when you submit, you will be sent back to the calendar.' }}
                </p>
            </div>

            <fieldset>
                <legend class="text-sm font-semibold">Duration</legend>
                <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                    @foreach ($durations as $duration)
                        <label class="{{ $radio }}">
                            <input type="radio" name="duration" value="{{ $duration->value }}" class="mt-1 accent-pool"
                                   @checked(old('duration', 'whole_day') === $duration->value)>
                            <span>
                                <span class="block font-semibold">{{ $duration->label() }}</span>
                                @if ($duration->value === 'half_day')
                                    <span class="block text-sm text-ink/60">Still reserves the whole date.</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('duration')<p class="mt-1.5 text-sm text-alert">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset>
                <legend class="text-sm font-semibold">Booking type</legend>
                <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                    @foreach ($types as $type)
                        <label class="{{ $radio }}">
                            <input type="radio" name="booking_type" value="{{ $type->value }}" class="mt-1 accent-pool"
                                   @checked(old('booking_type') === $type->value)>
                            <span>
                                <span class="block font-semibold">{{ $type->label() }}</span>
                                <span class="block text-sm text-ink/60">
                                    {{ $hints[$type->value] }}. {{ $type->paxCap() ? 'Up to '.$type->paxCap().' guests.' : 'No guest limit.' }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('booking_type')<p class="mt-1.5 text-sm text-alert">{{ $message }}</p>@enderror
            </fieldset>

            <x-field name="pax" label="Number of guests" type="number" inputmode="numeric" />
            <x-field name="occasion" label="Occasion" hint="For example: Birthday, Reunion, Family getaway." />

            <button type="submit" class="w-full rounded-lg bg-pool px-4 py-3 text-base font-semibold text-white hover:bg-ink">
                Hold this date
            </button>
        </form>
    </div>
</x-layouts.app>
