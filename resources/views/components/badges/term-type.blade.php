@props([
    'tipo',
    'size' => 'sm',
])

<flux:badge {{ $attributes }} :size="$size" :color="$tipo->badgeColor()" :icon="$tipo->icon()">
    {{ $tipo->label() }}
</flux:badge>
