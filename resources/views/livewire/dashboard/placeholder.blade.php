<div class="flex flex-col gap-6" role="status" aria-label="Carregando indicadores">
    <div class="flex items-center gap-4">
        <flux:skeleton animate="shimmer" class="size-11 rounded-xl" />
        <flux:skeleton.group animate="shimmer" class="w-64 space-y-2">
            <flux:skeleton.line size="lg" class="w-40" />
            <flux:skeleton.line />
        </flux:skeleton.group>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (range(1, 4) as $card)
            <x-ui.stat-card-skeleton wire:key="skeleton-stat-{{ $card }}" />
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="h-56 surface-card p-5">
            <flux:skeleton.group animate="shimmer" class="space-y-4">
                <flux:skeleton.line class="w-32" />
                <flux:skeleton class="h-10 w-full" />
                <flux:skeleton.line class="w-1/2" />
            </flux:skeleton.group>
        </div>

        <div class="h-56 surface-card p-5 lg:col-span-2">
            <flux:skeleton.group animate="shimmer" class="space-y-4">
                <flux:skeleton.line class="w-40" />
                @foreach (range(1, 4) as $line)
                    <flux:skeleton.line wire:key="skeleton-line-{{ $line }}" />
                @endforeach
            </flux:skeleton.group>
        </div>
    </div>
</div>
