<x-layouts.auth title="Create account">
    <h1 class="mt-8 font-display text-3xl font-bold">Create your account</h1>
    <p class="mt-2 text-ink/70">You need an account to reserve a date. It takes a minute.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-7 space-y-5" novalidate>
        @csrf
        <x-field name="name" label="Full name" autocomplete="name" />
        <x-field name="phone" label="Contact number" type="tel" autocomplete="tel" inputmode="tel"
                 hint="The resort uses this to reach you about your reservation." />
        <x-field name="email" label="Email" type="email" autocomplete="email" inputmode="email" />
        <x-field name="password" label="Password" type="password" autocomplete="new-password"
                 hint="At least 8 characters." />
        <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />

        <button type="submit" class="w-full rounded-lg bg-pool px-4 py-3 text-base font-semibold text-white hover:bg-ink">
            Create account
        </button>
    </form>

    <p class="mt-6 text-sm text-ink/70">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-pool hover:underline">Log in</a>
    </p>
</x-layouts.auth>
