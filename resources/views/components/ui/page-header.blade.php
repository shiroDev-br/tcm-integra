@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<header {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="flex min-w-0 items-center gap-4">
        {{ $before ?? '' }}

        @if ($icon)
            <x-ui.icon-chip :icon="$icon" color="solid" size="lg" />
        @endif

        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="truncate">{{ $title }}</flux:heading>

            @if ($subtitle)
                <flux:text class="mt-0.5">{{ $subtitle }}</flux:text>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>
