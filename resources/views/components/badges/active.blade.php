{{-- Badge de situação ativo/inativo com indicador colorido. --}}
@props([
    'active',
    'on' => 'Ativo',
    'off' => 'Inativo',
])

<flux:badge {{ $attributes }} size="sm" :color="$active ? 'green' : 'red'">
    <span @class(['me-1.5 size-1.5 rounded-full', 'bg-green-500' => $active, 'bg-red-500' => ! $active])></span>
    {{ $active ? $on : $off }}
</flux:badge>
