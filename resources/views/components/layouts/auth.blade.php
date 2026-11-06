<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | ZKs Private Resort</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="grid min-h-screen md:grid-cols-[1fr_minmax(26rem,32rem)] lg:grid-cols-[1.1fr_1fr]">
        <div class="relative hidden md:block">
            <img src="{{ asset('images/rooms-night.jpg') }}" alt="A-frame room at ZKs Private Resort, lit up at night"
                 class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-ink/70 via-transparent to-transparent"></div>
            <p class="absolute bottom-8 left-8 right-8 max-w-sm font-display text-2xl font-semibold leading-snug text-white">
                The whole resort is yours for the day.
            </p>
        </div>

        <div class="flex flex-col justify-center px-6 py-10 sm:px-10">
            <div class="mx-auto w-full max-w-sm">
                <a href="{{ route('home') }}" aria-label="ZKs Private Resort, home">
                    <img src="{{ asset('images/logo.png') }}" alt="ZKs Private Resort" class="h-20 w-auto">
                </a>

                {{ $slot }}

                <p class="mt-8 text-sm">
                    <a href="{{ route('home') }}" class="font-medium text-pool hover:underline">Back to Home</a>
                </p>
            </div>
        </div>
    </div>

    <x-chat-widget />
</body>
</html>
