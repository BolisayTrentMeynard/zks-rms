<x-layouts.app title="Profile">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">Your profile</h1>
        <p class="mt-2 text-ink/70">
            {{ $user->isAdmin() ? 'The resort account details.' : 'The contact details the resort uses for your reservations.' }}
        </p>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-lg border border-palm/30 bg-palm/10 px-4 py-3 text-sm font-medium text-palm">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" class="mt-7 space-y-5 rounded-xl border border-line bg-white p-6" novalidate>
            @csrf
            @method('PUT')
            <x-field name="name" label="Full name" :value="$user->name" autocomplete="name" />
            <x-field name="phone" label="Contact number" type="tel" :value="$user->phone" autocomplete="tel" inputmode="tel" />
            <x-field name="email" label="Email" type="email" :value="$user->email" autocomplete="email" inputmode="email" />

            <button type="submit" class="rounded-lg bg-pool px-5 py-2.5 font-semibold text-white hover:bg-ink">Save changes</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="rounded-lg border border-line bg-white px-5 py-2.5 font-semibold text-ink hover:border-pool hover:text-pool">Log out</button>
        </form>
    </div>
</x-layouts.app>
