<x-layouts.app title="Availability">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">Availability</h1>
        <p class="mt-2 max-w-prose text-ink/70">
            Pick an open date to reserve it. We take one group per date, so a reserved date is closed for every booking type.
        </p>
        @if (auth()->user()?->isAdmin())
            <p class="mt-2 max-w-prose font-medium text-pool">
                Admin: pick an open date, today or later, to enter a walk-in. It is Confirmed straight away.
            </p>
        @endif

        @if (session('error'))
            <div role="alert" class="mt-6 rounded-lg border border-alert/30 bg-alert/10 px-4 py-3 text-sm font-medium text-alert">
                {{ session('error') }}
            </div>
        @endif

        <div class="mt-6 rounded-xl border border-line bg-white p-3 sm:p-5">
            <div class="flex items-center justify-between gap-2">
                @if ($prev)
                    <a href="{{ route('availability', ['month' => $prev]) }}"
                       class="rounded-lg border border-line px-3 py-2 text-sm font-semibold hover:border-pool hover:text-pool">Previous</a>
                @else
                    <span class="invisible rounded-lg border px-3 py-2 text-sm font-semibold" aria-hidden="true">Previous</span>
                @endif

                <h2 class="font-display text-xl font-bold sm:text-2xl">{{ $month->format('F Y') }}</h2>

                <a href="{{ route('availability', ['month' => $next]) }}"
                   class="rounded-lg border border-line px-3 py-2 text-sm font-semibold hover:border-pool hover:text-pool">Next</a>
            </div>

            <div class="mt-4 grid grid-cols-7 gap-1 text-center text-xs font-semibold text-ink/60" aria-hidden="true">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                    <div>{{ $weekday }}</div>
                @endforeach
            </div>

            <div class="mt-1 grid grid-cols-7 gap-1">
                @foreach ($cells as $cell)
                    @if ($cell === null)
                        <div></div>
                        @continue
                    @endif

                    @php
                        $tag = $cell['href'] ? 'a' : 'div';
                        $box = match ($cell['kind']) {
                            'open' => 'border-sky/40 bg-white'.($cell['href'] ? ' hover:border-pool hover:bg-mist' : ''),
                            'closed' => 'border-transparent bg-mist text-ink/35',
                            'own' => 'border-pool/40 bg-white hover:border-pool',
                            default => 'border-line bg-deck/40', // reserved, admin
                        };
                    @endphp

                    <{{ $tag }}
                        @if ($cell['href']) href="{{ $cell['href'] }}" @endif
                        class="flex min-h-[4.5rem] min-w-0 flex-col items-start gap-0.5 rounded-lg border p-1.5 sm:min-h-[5.5rem] sm:p-2 {{ $box }}">
                        <span class="text-sm font-semibold">
                            <span class="sr-only">{{ $cell['date']->format('F') }}</span>{{ $cell['date']->day }}
                        </span>

                        @switch($cell['kind'])
                            @case('open')
                                <span class="text-[10px] font-medium text-palm sm:text-xs">Available</span>
                                @break
                            @case('reserved')
                                <span class="text-[10px] font-medium text-ink/70 sm:text-xs">Reserved</span>
                                @break
                            @case('own')
                                <x-status-badge :status="$cell['reservation']->status" />
                                <span class="text-[10px] text-ink/60 sm:text-xs">Your booking</span>
                                @break
                            @case('admin')
                                <x-status-badge :status="$cell['reservation']->status" />
                                <span class="w-full truncate text-[10px] text-ink/70 sm:text-xs">{{ $cell['reservation']->bookerName() }}</span>
                                @break
                        @endswitch
                    </{{ $tag }}>
                @endforeach
            </div>
        </div>

        <ul class="mt-5 space-y-1.5 text-sm text-ink/70">
            <li><span class="font-semibold text-palm">Available</span>: open to book. Online bookings start from {{ $earliest->format('F j, Y') }}.</li>
            <li><span class="font-semibold text-ink">Reserved</span>: a group already has this date. A reserved date may reopen if a hold expires.</li>
            <li>Greyed dates are too soon or already past for online booking.</li>
        </ul>

        @guest
            <p class="mt-6 text-ink/70">
                You will need to <a href="{{ route('login') }}" class="font-semibold text-pool hover:underline">log in</a>
                or <a href="{{ route('register') }}" class="font-semibold text-pool hover:underline">create an account</a> before you can reserve a date.
                Choosing an open date will take you there and bring you back.
            </p>
        @endguest
    </div>
</x-layouts.app>
