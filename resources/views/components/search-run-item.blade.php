@props([
    'busca',
])

<li {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 py-3') }}>
    <div class="flex min-w-0 items-center gap-3">
        <x-ui.icon-chip icon="calendar-days" color="zinc" size="sm" />

        <div class="min-w-0">
            <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                @periodo($busca->periodo_inicio, $busca->periodo_fim)
            </div>
            <flux:text size="sm">
                {{ $busca->termos }} {{ $busca->termos === 1 ? 'cadastro' : 'cadastros' }} · @dataHora($busca->created_at)
            </flux:text>
        </div>
    </div>

    <x-badges.search-status :status="$busca->status" />
</li>
