<x-layouts.app title="Notifications">
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-3xl font-bold">Notifications</h1>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-pool hover:border-pool">Mark all as read</button>
                </form>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <p class="mt-8 rounded-xl border border-line bg-white p-6 text-ink/70">No notifications yet.</p>
        @else
            <ul class="mt-6 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white">
                @foreach ($notifications as $notification)
                    <li>
                        <a href="{{ route('notifications.open', $notification) }}"
                           class="flex items-start gap-3 px-4 py-3.5 hover:bg-mist {{ $notification->isUnread() ? 'bg-sky/10' : '' }}">
                            <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full {{ $notification->isUnread() ? 'bg-pool' : 'bg-transparent' }}" aria-hidden="true"></span>
                            <span class="min-w-0">
                                <span class="block text-[15px] {{ $notification->isUnread() ? 'font-semibold' : 'text-ink/80' }}">
                                    @if ($notification->isUnread())<span class="sr-only">Unread: </span>@endif{{ $notification->message }}
                                </span>
                                <time datetime="{{ $notification->created_at->toIso8601String() }}" class="mt-0.5 block text-sm text-ink/60">{{ $notification->created_at->format('M j, Y, g:i A') }}</time>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
