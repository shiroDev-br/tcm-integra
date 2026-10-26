@props([
    'label',
    'value',
    'icon',
    'color' => 'navy',
    'hint' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class(['group relative flex items-start justify-between gap-4 overflow-hidden surface-card p-5', 'surface-card-interactive' => $href]) }}
>
    <div class="min-w-0 space-y-1">
        <flux:text class="font-medium">{{ $label }}</flux:text>
        <div class="truncate text-3xl font-semibold tracking-tight text-zinc-900 tabular-nums dark:text-white">{{ $value }}</div>

        @if ($hint)
            <flux:text size="sm" class="truncate">{{ $hint }}</flux:text>
        @endif
    </div>

    <x-ui.icon-chip :icon="$icon" :color="$color" size="lg" />
</{{ $tag }}>
