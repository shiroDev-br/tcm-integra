{{-- Como o prazo foi contado (dias e início), avisando quando o vencimento foi ajustado à mão. --}}
@props([
    'prazo',
])

<span {{ $attributes }}>
    @if ($prazo->ajustado)
        Vencimento ajustado manualmente ·
    @endif
    {{ $prazo->dias }} {{ $prazo->contagem->label() }} a partir de @data($prazo->data_inicio)@if ($prazo->isEstimativa()) (estimado)@endif
</span>
