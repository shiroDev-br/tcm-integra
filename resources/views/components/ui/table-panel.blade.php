{{-- Envolve uma flux:table em um painel com barra de progresso e overlay durante filtros e paginação. --}}
@props([
    'target' => null,
    'label' => 'Carregando registros...',
])

@php
    $target = collect(['gotoPage', 'nextPage', 'previousPage', 'setPage'])
        ->merge(array_filter(array_map('trim', explode(',', (string) $target))))
        ->implode(',');
@endphp

<div {{ $attributes->class('relative overflow-hidden surface-card') }}>
    <x-ui.progress-bar :target="$target" />

    <div class="px-4 pb-2 [&_[data-flux-pagination]]:px-1">
        {{ $slot }}
    </div>

    <x-ui.loading-overlay :target="$target" :label="$label" />
</div>
