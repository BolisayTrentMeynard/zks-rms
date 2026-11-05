@php
    $link = fn (bool $active) => 'rounded-md px-3 py-2 text-[15px] font-medium '.($active
        ? 'text-pool underline decoration-sky decoration-2 underline-offset-8'
        : 'text-ink/80 hover:text-pool');
@endphp

<header class="border-b border-line bg-white">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-2.5 sm:px-6" aria-label="Main">
        <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="ZKs Private Resort, home">
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-12 w-auto">
        </a>

        <button type="button" data-nav-toggle aria-expanded="false" aria-controls="main-menu"
                class="rounded-md p-2 text-ink hover:bg-mist md:hidden">
            <span class="sr-only">Menu</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
            </svg>
        </button>

        <div id="main-menu" data-nav-menu
             class="absolute inset-x-0 top-[4.25rem] z-10 hidden border-b border-line bg-white px-4 pb-4 pt-2 shadow-sm md:static md:flex md:items-center md:gap-1 md:border-0 md:bg-transparent md:p-0 md:shadow-none">
            <div class="flex flex-col md:flex-row md:items-center md:gap-1">
                <a href="{{ route('home') }}" class="{{ $link(request()->routeIs('home')) }}">Home</a>
                <a href="{{ route('availability') }}" class="{{ $link(request()->routeIs('availability', 'reservations.*')) }}">Availability</a>

                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="{{ $link(request()->routeIs('admin.*')) }}">Dashboard</a>
                    @endif
                    <a href="{{ route('profile.edit') }}" class="{{ $link(request()->routeIs('profile.*')) }}">Profile</a>
                @endauth
            </div>

            <div class="mt-3 flex flex-col gap-2 border-t border-line pt-3 md:ml-3 md:mt-0 md:flex-row md:items-center md:border-0 md:pt-0">
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-[15px] font-medium text-ink/80 hover:text-pool md:w-auto">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="{{ $link(request()->routeIs('login')) }}">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-pool px-4 py-2 text-center text-[15px] font-semibold text-white hover:bg-ink">Create account</a>
                @endauth
            </div>
        </div>
    </nav>
</header>
