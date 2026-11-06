@php($topics = \App\Support\Faq::topics())

<x-layouts.app title="FAQ">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">Questions and answers</h1>
        <p class="mt-2 max-w-prose text-ink/70">How booking works at ZKs Private Resort. For anything not covered here, {{ lcfirst(\App\Support\Faq::contact()) }}</p>

        <nav aria-label="Topics" class="mt-6 flex flex-wrap gap-2">
            @foreach ($topics as $topic)
                <a href="#{{ $topic['id'] }}" class="rounded-full border border-line bg-white px-3.5 py-1.5 text-sm font-semibold text-pool hover:border-pool">{{ $topic['title'] }}</a>
            @endforeach
        </nav>

        <div class="mt-8 space-y-5">
            @foreach ($topics as $topic)
                <section id="{{ $topic['id'] }}" class="scroll-mt-6 rounded-xl border border-line bg-white p-5 sm:p-6">
                    <h2 class="font-display text-xl font-bold">{{ $topic['title'] }}</h2>
                    <div class="mt-3 space-y-2 text-[15px] text-ink/85">
                        @foreach ($topic['answer'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
