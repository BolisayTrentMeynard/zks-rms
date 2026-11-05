<x-layouts.app title="Availability">
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="font-display text-3xl font-bold">Availability</h1>
        <p class="mt-3 max-w-prose text-ink/70">
            The date calendar and the reservation form are the next part of the system.
            Until then, this page is a placeholder.
        </p>
        @guest
            <p class="mt-6 text-ink/70">
                You will need to <a href="{{ route('login') }}" class="font-semibold text-pool hover:underline">log in</a>
                or <a href="{{ route('register') }}" class="font-semibold text-pool hover:underline">create an account</a> before you can reserve a date.
            </p>
        @endguest
    </div>
</x-layouts.app>
