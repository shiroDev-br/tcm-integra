@props([
    'status',
    'size' => 'sm',
])

<flux:badge {{ $attributes }} :size="$size" :color="$status->badgeColor()" :icon="$status->icon()">
    {{ $status->label() }}
</flux:badge>
