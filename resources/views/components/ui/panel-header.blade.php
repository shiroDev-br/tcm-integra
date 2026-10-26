@props([
    'heading',
    'subheading' => null,
    'icon' => null,
])

<div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-4 dark:border-white/10') }}>
    <div class="flex min-w-0 items-center gap-3">
        @if ($icon)
            <flux:icon :icon="$icon" variant="outline" class="size-5 shrink-0 text-navy-700 dark:text-navy-300" />
        @endif

        <div class="min-w-0">
            <flux:heading size="lg" class="truncate">{{ $heading }}</flux:heading>

            @if ($subheading)
                <flux:text size="sm">{{ $subheading }}</flux:text>
            @endif
        </div>
    </div>

    {{ $slot }}
</div>
