<x-layouts.app title="Home">
    {{-- Hero --}}
    <section class="relative isolate flex min-h-[30rem] items-end overflow-hidden bg-ink sm:min-h-[34rem] lg:min-h-[38rem]">
        <img src="{{ asset('images/hero-pool.jpg') }}" alt="The pool at ZKs Private Resort, shaded by sails"
             class="absolute inset-0 -z-10 h-full w-full object-cover">
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-ink/85 via-ink/35 to-transparent"></div>

        <div class="mx-auto w-full max-w-6xl px-4 pb-10 sm:px-6 sm:pb-14">
            <h1 class="max-w-2xl font-display text-4xl font-bold leading-[1.05] text-white sm:text-5xl lg:text-6xl">
                The whole resort is yours for the day.
            </h1>
            <p class="mt-4 max-w-xl text-lg text-white/90">
                Check which dates are open, reserve in a few taps, and send your GCash downpayment proof here instead of in Messenger.
            </p>
            <a href="{{ route('availability') }}"
               class="mt-7 inline-block rounded-lg bg-sky px-7 py-3.5 text-base font-bold text-ink hover:bg-white">
                Book Now
            </a>
        </div>
    </section>

    {{-- What you can book --}}
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <h2 class="font-display text-2xl font-bold sm:text-3xl">What you can book</h2>
        <p class="mt-2 max-w-prose text-ink/70">One group per date, so the resort is private to you whichever you choose.</p>

        <div class="mt-8 grid gap-6 sm:grid-cols-3">
            @foreach ([
                ['images/events-night.jpg', 'Open hall at night with string lights', 'Events and occasions', 'A venue hall for birthdays, reunions and other gatherings.'],
                ['images/rooms-day.jpg', 'A-frame room with two wicker chairs', 'Rooms', 'Living area and private bathroom, for up to 12 guests.'],
                ['images/pool-wide.jpg', 'The outdoor pool under a shade sail', 'Pool', 'The outdoor pool, with no limit on the number of guests.'],
            ] as [$img, $alt, $name, $text])
                <figure>
                    <img src="{{ asset($img) }}" alt="{{ $alt }}" loading="lazy"
                         class="aspect-[4/3] w-full rounded-xl object-cover">
                    <figcaption class="mt-3">
                        <span class="block font-display text-lg font-semibold">{{ $name }}</span>
                        <span class="block text-ink/70">{{ $text }}</span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    {{-- How booking works: a real sequence --}}
    <section class="border-t border-line bg-white">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="font-display text-2xl font-bold sm:text-3xl">How booking works</h2>

            <ol class="mt-8 grid gap-8 sm:grid-cols-3">
                <li>
                    <span class="font-display text-3xl font-bold text-sky" aria-hidden="true">1</span>
                    <h3 class="mt-1 font-semibold">Pick an open date</h3>
                    <p class="mt-1 text-ink/70">Log in, choose a date, a booking type and the number of guests. We hold the date for you for 24 hours.</p>
                </li>
                <li>
                    <span class="font-display text-3xl font-bold text-sky" aria-hidden="true">2</span>
                    <h3 class="mt-1 font-semibold">Send your GCash proof</h3>
                    <p class="mt-1 text-ink/70">Upload a screenshot of your downpayment before the 24 hours run out, or the date opens up again.</p>
                </li>
                <li>
                    <span class="font-display text-3xl font-bold text-sky" aria-hidden="true">3</span>
                    <h3 class="mt-1 font-semibold">Get your confirmation</h3>
                    <p class="mt-1 text-ink/70">The resort checks your proof and confirms. You can follow the status on your account.</p>
                </li>
            </ol>
        </div>
    </section>
</x-layouts.app>
