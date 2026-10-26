{{-- Card clicável de um termo monitorado com contador; mostra spinner enquanto `action` carrega. --}}
@props([
    'termo',
    'count',
    'singular',
    'plural',
    'action',
])

<button
    type="button"
    wire:click="{{ $action }}"
    wire:target="{{ $action }}"
    wire:loading.attr="disabled"
    wire:loading.class="pointer-events-none"
    {{ $attributes->class('group relative flex w-full flex-col gap-4 overflow-hidden surface-card surface-card-interactive p-5 text-start focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-hidden') }}
>
    <span class="absolute inset-y-0 start-0 w-1 bg-navy-700 opacity-0 transition group-hover:opacity-100 dark:bg-navy-400"></span>

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 space-y-2">
            <div class="truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ $termo->termo }}</div>
            <x-badges.term-type :tipo="$termo->tipo" />
        </div>

        <span class="text-3xl font-semibold tracking-tight text-navy-800 tabular-nums dark:text-navy-300">{{ $count }}</span>
    </div>

    <div class="flex items-center justify-between gap-2 text-sm text-zinc-500 dark:text-zinc-400">
        <span>{{ $count === 1 ? $singular : $plural }}</span>

        <span wire:loading.remove wire:target="{{ $action }}" class="flex items-center gap-1 font-medium text-navy-700 dark:text-navy-300">
            Abrir
            <flux:icon.arrow-right variant="micro" class="transition group-hover:translate-x-0.5" />
        </span>

        <span wire:loading.flex wire:target="{{ $action }}" class="hidden items-center gap-1 font-medium text-navy-700 dark:text-navy-300">
            <flux:icon.loading class="size-4" />
            Abrindo...
        </span>
    </div>
</button>
