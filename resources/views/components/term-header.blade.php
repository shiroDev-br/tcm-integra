{{-- Cabeçalho da listagem de um termo aberto, com botão de voltar para os cards. --}}
@props([
    'termo',
    'subtitle',
    'back' => 'voltarCards',
])

<x-ui.page-header :title="$termo->termo" :subtitle="$subtitle" :icon="$termo->tipo->icon()" {{ $attributes }}>
    <x-slot:before>
        <flux:button variant="ghost" icon="arrow-left" wire:click="{{ $back }}" class="shrink-0">
            Voltar
        </flux:button>
    </x-slot:before>

    <x-slot:actions>
        <x-badges.term-type :tipo="$termo->tipo" size="lg" />
    </x-slot:actions>
</x-ui.page-header>
