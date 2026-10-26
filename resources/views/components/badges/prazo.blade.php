{{-- Badge do tempo restante até o vencimento do prazo, com aviso quando a data é estimada. --}}
@props([
    'prazo',
])

<flux:badge {{ $attributes }} size="sm" :color="$prazo->badgeColor()">
    {{ $prazo->situacao() }}@if ($prazo->estimado)*@endif
</flux:badge>
