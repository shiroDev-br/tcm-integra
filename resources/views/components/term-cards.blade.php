{{-- Grade de cards dos termos ativos; `countAttribute` é a coluna de contagem carregada em cada termo. --}}
@props([
    'termos',
    'countAttribute',
    'singular',
    'plural',
    'emptyDescription',
])

@if ($termos->isEmpty())
    <x-ui.panel>
        <x-ui.empty-state icon="tag" title="Nenhum termo ativo cadastrado" :description="$emptyDescription">
            <flux:button variant="primary" icon="plus" :href="route('termos.index')" wire:navigate>Cadastrar termo</flux:button>
        </x-ui.empty-state>
    </x-ui.panel>
@else
    <div {{ $attributes->class('grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4') }}>
        @foreach ($termos as $termo)
            <x-term-card
                :termo="$termo"
                :count="(int) $termo->{$countAttribute}"
                :singular="$singular"
                :plural="$plural"
                action="abrirTermo({{ $termo->id }})"
                wire:key="card-{{ $countAttribute }}-{{ $termo->id }}"
            />
        @endforeach
    </div>
@endif
