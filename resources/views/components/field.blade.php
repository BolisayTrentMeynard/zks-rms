@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'hint' => null,
    'inputmode' => null,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-ink">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($inputmode) inputmode="{{ $inputmode }}" @endif
        required
        aria-describedby="{{ $name }}-note"
        @class([
            'mt-1.5 block w-full rounded-lg border bg-white px-3.5 py-2.5 text-base text-ink',
            'focus:border-pool focus:outline-none focus:ring-2 focus:ring-sky/40',
            'border-line' => ! $errors->has($name),
            'border-alert' => $errors->has($name),
        ])
    >
    <p id="{{ $name }}-note" class="mt-1.5 text-sm {{ $errors->has($name) ? 'text-alert' : 'text-ink/60' }}">
        {{ $errors->first($name) ?: $hint }}
    </p>
</div>
