<x-layouts.auth title="Log in">
    <h1 class="mt-8 font-display text-3xl font-bold">Log in</h1>
    <p class="mt-2 text-ink/70">Use your ZKs account to reserve a date and track your reservations.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" inputmode="email" />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" />

        <button type="submit" class="w-full rounded-lg bg-pool px-4 py-3 text-base font-semibold text-white hover:bg-ink">
            Log in
        </button>
    </form>

    <p class="mt-6 text-sm text-ink/70">
        New here?
        <a href="{{ route('register') }}" class="font-semibold text-pool hover:underline">Create an account</a>
    </p>
</x-layouts.auth>
