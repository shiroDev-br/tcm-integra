{{-- Painel de filtros de uma listagem; `target` lista as propriedades que disparam o indicador "Filtrando". --}}
@props([
    'target' => null,
])

<div {{ $attributes->class('relative surface-card p-4') }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        {{ $slot }}
    </div>

    <div
        wire:loading.delay.flex
        @if ($target) wire:target="{{ $target }}" @endif
        class="absolute end-4 top-3 hidden items-center gap-1.5 text-xs font-medium text-navy-700 dark:text-navy-300"
    >
        <flux:icon.loading class="size-3.5" />
        Filtrando...
    </div>
</div>
