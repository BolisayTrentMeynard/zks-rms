{{-- FAQ chat on every page. The answers (App\Support\Faq) are fetched once, when the panel is first opened. --}}
<div data-chat-widget data-chat-url="{{ route('chat.faq') }}" class="fixed bottom-4 right-4 z-30 flex flex-col items-end gap-3 print:hidden">
    <section data-chat-panel hidden role="dialog" aria-label="Ask about the resort"
             class="flex max-h-[min(34rem,calc(100vh-6rem))] w-[min(23rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 bg-pool px-4 py-3 text-white">
            <div>
                <p class="font-display text-lg font-bold leading-tight">Ask about the resort</p>
                <p class="mt-0.5 text-xs text-white/80">Quick answers about booking. This chat cannot book, change or cancel anything.</p>
            </div>
            <button type="button" data-chat-close class="rounded-md p-1 text-white/90 hover:bg-white/15" aria-label="Close chat">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
        </div>

        <div data-chat-log role="log" aria-live="polite" class="flex-1 space-y-2.5 overflow-y-auto px-4 py-3"></div>

        <div data-chat-topics class="flex flex-wrap gap-1.5 border-t border-line px-4 py-3"></div>

        <form data-chat-form class="flex items-center gap-2 border-t border-line px-3 py-3">
            <label for="chat-input" class="sr-only">Type your question</label>
            <input id="chat-input" data-chat-input type="text" maxlength="300" autocomplete="off" placeholder="Type your question"
                   class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-pool focus:outline-none focus:ring-2 focus:ring-sky/40">
            <button type="submit" class="rounded-lg bg-pool px-3.5 py-2 text-sm font-semibold text-white hover:bg-ink">Send</button>
        </form>

        <p class="border-t border-line bg-mist px-4 py-2 text-xs text-ink/70">
            <a href="{{ route('faq') }}" class="font-semibold text-pool hover:underline">See all questions and answers</a>
        </p>
    </section>

    <button type="button" data-chat-toggle aria-expanded="false"
            class="flex items-center gap-2 rounded-full bg-pool px-4 py-3 text-sm font-semibold text-white shadow-lg hover:bg-ink">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
        </svg>
        <span>Ask a question</span>
    </button>
</div>
