@php
    $filtros = 'busca,filtroSituacao';
@endphp

<x-ui.filter-bar :target="$filtros">
    <flux:field class="flex-1">
        <flux:label>Busca</flux:label>
        <flux:input wire:model.live.debounce.400ms="busca" icon="magnifying-glass" placeholder="Número, autor ou situação" clearable />
    </flux:field>

    <flux:field class="sm:w-48">
        <flux:label>Situação</flux:label>
        <flux:select wire:model.live="filtroSituacao">
            <flux:select.option value="">Todas</flux:select.option>
            <flux:select.option value="localizado">Localizado</flux:select.option>
            <flux:select.option value="nao-localizado">Não localizado</flux:select.option>
        </flux:select>
    </flux:field>
</x-ui.filter-bar>

<x-ui.table-panel :target="$filtros" label="Buscando processos...">
    <flux:table :paginate="$this->processos">
        <flux:table.columns>
            <flux:table.column>Número</flux:table.column>
            <flux:table.column>Autor</flux:table.column>
            <flux:table.column>Data de entrada</flux:table.column>
            <flux:table.column>Prazo</flux:table.column>
            <flux:table.column>Situação</flux:table.column>
            <flux:table.column>Consulta</flux:table.column>
            <flux:table.column align="end">Ações</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->processos as $processo)
                <flux:table.row :key="$processo->numero">
                    <flux:table.cell variant="strong">{{ $processo->numero }}</flux:table.cell>
                    <flux:table.cell class="max-w-64 truncate">{{ $processo->autor ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="tabular-nums">@data($processo->data_entrada)</flux:table.cell>
                    <flux:table.cell>
                        @if ($processo->prazoAtual !== null)
                            <div class="tabular-nums">@data($processo->prazoAtual->data_vencimento)</div>
                            <x-badges.prazo :prazo="$processo->prazoAtual" />
                        @else
                            <span class="text-zinc-500 dark:text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="max-w-64 truncate">{{ $processo->situacao ?? '—' }}</div>
                        @if ($processo->situacao_data !== null)
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">@data($processo->situacao_data)</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <x-badges.active :active="$processo->encontrado" on="Localizado" off="Não localizado" />
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <x-ui.action-button icon="eye" label="Ver detalhes do processo" color="sky" wire:click="verDetalhe('{{ $processo->numero }}')" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <x-ui.empty-state icon="folder-open" title="Nenhum processo encontrado para este termo." description="Ajuste a busca ou o filtro de situação." />
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:text class="px-4 py-3 text-xs">
        * Data estimada: a contagem começa na notificação eletrônica do e-TCM (não divulgada) ou em dias úteis sem considerar feriados; usamos a data de publicação no Diário Oficial.
    </flux:text>
</x-ui.table-panel>
