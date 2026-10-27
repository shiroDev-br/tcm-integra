@php
    $andamento = $detalhe->andamentoItens();
    $vinculos = $detalhe->vinculosPorPeriodo();
    $prazoAtual = $detalhe->prazos->first();
@endphp

<dl class="grid gap-3 sm:grid-cols-2">
    <x-ui.detail-item label="Autor">{{ $detalhe->autor ?? '—' }}</x-ui.detail-item>
    <x-ui.detail-item label="Município (portal do TCM)">{{ $detalhe->municipio ?? 'Não informado pelo portal' }}</x-ui.detail-item>
    <x-ui.detail-item label="Data de entrada">@data($detalhe->data_entrada)</x-ui.detail-item>
    <x-ui.detail-item label="Situação">
        {{ $detalhe->situacao ?? '—' }}
        @if ($detalhe->situacao_data !== null)
            (@data($detalhe->situacao_data))
        @endif
    </x-ui.detail-item>
    <x-ui.detail-item label="Prazo">
        @if ($prazoAtual !== null)
            <div class="flex flex-wrap items-center gap-2">
                <span class="tabular-nums">@data($prazoAtual->data_vencimento)</span>
                <x-badges.prazo :prazo="$prazoAtual" />
            </div>
            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $prazoAtual->dias }} {{ $prazoAtual->contagem->label() }} a partir de @data($prazoAtual->data_inicio)@if ($prazoAtual->estimado) (estimado)@endif
            </div>
        @else
            Sem prazo publicado
        @endif
    </x-ui.detail-item>
    <x-ui.detail-item label="Meio">{{ $detalhe->meio ?? '—' }}</x-ui.detail-item>
    <x-ui.detail-item label="Complemento" class="sm:col-span-2">{{ $detalhe->complemento ?? '—' }}</x-ui.detail-item>
    <x-ui.detail-item label="Consultado em" class="sm:col-span-2">@dataHora($detalhe->consultado_em)</x-ui.detail-item>
</dl>

@if ($detalhe->prazos->isNotEmpty())
    <div class="space-y-3">
        <flux:heading>Prazos publicados</flux:heading>

        <ul class="space-y-3">
            @foreach ($detalhe->prazos as $prazo)
                <li wire:key="prazo-{{ $prazo->id }}" class="space-y-1 rounded-lg border border-zinc-200 p-3 text-sm dark:border-white/10">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">
                            Vence em @data($prazo->data_vencimento)
                        </span>
                        <x-badges.prazo :prazo="$prazo" />
                    </div>
                    <div class="text-zinc-500 dark:text-zinc-400">
                        {{ $prazo->dias }} {{ $prazo->contagem->label() }} a partir de @data($prazo->data_inicio)@if ($prazo->estimado) (estimado)@endif
                        ·
                        <flux:link :href="$prazo->pdfUrl()" external>Diário Oficial, página {{ $prazo->pagina }}</flux:link>
                    </div>
                    <p class="text-zinc-600 italic dark:text-zinc-300">“{{ $prazo->trecho }}”</p>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if (count($andamento) > 0)
    <div class="space-y-3">
        <flux:heading>Andamento</flux:heading>

        <ol class="relative space-y-4 border-s border-zinc-200 ps-5 dark:border-white/10">
            @foreach ($andamento as $item)
                <li wire:key="andamento-{{ $loop->index }}" class="relative">
                    <span class="absolute -start-[25px] top-1.5 size-2.5 rounded-full bg-navy-600 ring-4 ring-white dark:bg-navy-400 dark:ring-zinc-800"></span>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item['unidade'] ?? '—' }}</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">@data($item['data'] ?? null)</span>
                    </div>
                    <div class="text-sm">{{ $item['situacao'] ?? '—' }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $item['observacao'] ?? '' }}</div>
                </li>
            @endforeach
        </ol>
    </div>
@endif

@if ($vinculos->isNotEmpty())
    <div class="space-y-3">
        <flux:heading>Cadastros vinculados</flux:heading>

        <div class="flex flex-wrap gap-2">
            @foreach ($vinculos as $vinculo)
                <flux:badge
                    wire:key="vinculo-{{ $vinculo->termo_id }}-{{ $vinculo->periodo_inicio }}-{{ $loop->index }}"
                    size="sm"
                    color="sky"
                    icon="tag"
                >
                    {{ $vinculo->termo?->termo }} (@periodo($vinculo->periodo_inicio, $vinculo->periodo_fim))
                </flux:badge>
            @endforeach
        </div>
    </div>
@endif
