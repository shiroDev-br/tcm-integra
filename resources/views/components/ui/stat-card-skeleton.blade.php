<div {{ $attributes->class('flex items-start justify-between gap-4 surface-card p-5') }}>
    <flux:skeleton.group animate="shimmer" class="flex-1 space-y-3">
        <flux:skeleton.line class="w-24" />
        <flux:skeleton class="h-8 w-16" />
        <flux:skeleton.line class="w-32" />
    </flux:skeleton.group>

    <flux:skeleton animate="shimmer" class="size-11 rounded-xl" />
</div>
