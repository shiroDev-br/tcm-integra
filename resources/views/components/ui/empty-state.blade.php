@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center gap-3 px-6 py-12 text-center') }}>
    <x-ui.icon-chip :icon="$icon" size="xl" halo />

    <div class="max-w-sm space-y-1 whitespace-normal">
        <flux:heading>{{ $title }}</flux:heading>

        @if ($description)
            <flux:text>{{ $description }}</flux:text>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
