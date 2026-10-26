@php
    $filtros = 'busca,inicio,fim,filtroSuplemento';
@endphp

<div class="flex flex-col gap-6">
    <x-ui.page-header title="Edições" subtitle="Edições do Diário Oficial varridas nas coletas" icon="newspaper" />

    <x-ui.filter-bar :target="$filtros">
        <flux:field class="flex-1">
            <flux:label>Busca</flux:label>
            <flux:input wire:model.live.debounce.400ms="busca" icon="magnifying-glass" placeholder="Data, número ou tipo" clearable />
        </flux:field>

        <flux:field class="sm:w-44">
            <flux:label>Início</flux:label>
            <flux:input type="date" wire:model.live="inicio" />
        </flux:field>

        <flux:field class="sm:w-44">
            <flux:label>Fim</flux:label>
            <flux:input type="date" wire:model.live="fim" />
        </flux:field>

        <flux:field class="sm:w-44">
            <flux:label>Suplemento</flux:label>
            <flux:select wire:model.live="filtroSuplemento">
                <flux:select.option value="">Todas</flux:select.option>
                <flux:select.option value="sim">Sim</flux:select.option>
                <flux:select.option value="nao">Não</flux:select.option>
            </flux:select>
        </flux:field>
    </x-ui.filter-bar>

    <x-ui.table-panel :target="$filtros" label="Buscando edições...">
        <flux:table :paginate="$this->edicoes">
            <flux:table.columns>
                <flux:table.column>ID</flux:table.column>
                <flux:table.column>Data</flux:table.column>
                <flux:table.column>Termos encontrados</flux:table.column>
                <flux:table.column>Número</flux:table.column>
                <flux:table.column>Páginas</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column>Suplemento</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->edicoes as $edicao)
                    <flux:table.row :key="$edicao->id">
                        <flux:table.cell class="text-zinc-500 tabular-nums dark:text-zinc-400">#{{ $edicao->id }}</flux:table.cell>
                        <flux:table.cell variant="strong" class="tabular-nums">@data($edicao->data)</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($edicao->termosEncontrados() as $termo)
                                    <flux:badge wire:key="edicao-{{ $edicao->id }}-{{ $termo }}" size="sm" color="sky" icon="tag">{{ $termo }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $edicao->numero ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $edicao->paginas ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $edicao->tipo_edicao_nome ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$edicao->suplemento ? 'amber' : 'zinc'">
                                {{ $edicao->suplemento ? 'Sim' : 'Não' }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <x-ui.empty-state icon="newspaper" title="Nenhuma edição com termos encontrados." description="Ajuste os filtros ou execute uma nova coleta." />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.table-panel>
</div>
