@props(['status', 'large' => false])

@php
    $classes = match ($status->value) {
        'slot_held' => 'bg-amber-100 text-amber-900',
        'verifying_payment' => 'bg-sky/15 text-pool',
        'confirmed' => 'bg-palm/15 text-palm',
        'action_needed' => 'bg-alert/10 text-alert',
        default => 'bg-slate-200 text-slate-700', // Expired, Cancelled
    };
@endphp

<span {{ $attributes->class([
    'inline-block rounded-full font-semibold leading-tight',
    'px-2 py-0.5 text-[10px] sm:text-xs' => ! $large,
    'px-3 py-1 text-sm' => $large,
    $classes,
]) }}>{{ $status->label() }}</span>
