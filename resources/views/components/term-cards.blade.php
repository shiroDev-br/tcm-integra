{{-- Grade de cards dos termos ativos; `countAttribute` (e o opcional `secondaryAttribute`) são colunas de contagem carregadas em cada termo. --}}
@props([
    'termos',
    'countAttribute',
    'singular',
    'plural',
    'emptyDescription',
    'secondaryAttribute' => null,
    'secondarySingular' => null,
    'secondaryPlural' => null,
    'novo' => false,
])

@if ($termos->isEmpty())
    <x-ui.panel>
        <x-ui.empty-state icon="tag" title="Nenhum cadastro ativo" :description="$emptyDescription">
            <flux:button variant="primary" icon="plus" :href="route('cadastros.index')" wire:navigate>Novo cadastro</flux:button>
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
                :secondary="$secondaryAttribute === null ? null : trans_choice('{1} :count '.$secondarySingular.'|[0,*] :count '.$secondaryPlural, (int) $termo->{$secondaryAttribute})"
                :novo="$novo"
                action="abrirTermo({{ $termo->id }})"
                wire:key="card-{{ $novo ? 'novo' : $countAttribute }}-{{ $termo->id }}"
            />
        @endforeach
    </div>
@endif
