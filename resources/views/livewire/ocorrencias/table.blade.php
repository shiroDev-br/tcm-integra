@use('App\Enums\OccurrenceType')

@php
    $filtros = 'tipo,periodo_inicio,periodo_fim,busca_texto';
@endphp

<x-ui.filter-bar :target="$filtros">
    <flux:field class="sm:w-44">
        <flux:label>Tipo</flux:label>
        <flux:select wire:model.live="tipo">
            <flux:select.option value="">Todos</flux:select.option>
            @foreach ($tipos as $tipoCase)
                <flux:select.option wire:key="filtro-tipo-{{ $tipoCase->value }}" :value="$tipoCase->value">
                    {{ $tipoCase->label() }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>

    <flux:field class="sm:w-44">
        <flux:label>Início</flux:label>
        <flux:input type="date" wire:model.live="periodo_inicio" />
    </flux:field>

    <flux:field class="sm:w-44">
        <flux:label>Fim</flux:label>
        <flux:input type="date" wire:model.live="periodo_fim" />
    </flux:field>

    <flux:field class="flex-1">
        <flux:label>Texto</flux:label>
        <flux:input wire:model.live.debounce.500ms="busca_texto" icon="magnifying-glass" placeholder="Trecho ou contexto" clearable />
    </flux:field>
</x-ui.filter-bar>

<x-ui.table-panel :target="$filtros" label="Buscando ocorrências...">
    <flux:table :paginate="$this->ocorrencias">
        <flux:table.columns>
            <flux:table.column>Edição</flux:table.column>
            <flux:table.column>Página</flux:table.column>
            <flux:table.column>Tipo</flux:table.column>
            <flux:table.column>Trecho</flux:table.column>
            <flux:table.column align="end">PDF</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->ocorrencias as $ocorrencia)
                <flux:table.row :key="$ocorrencia->id">
                    <flux:table.cell variant="strong">@data($ocorrencia->edicao_data)</flux:table.cell>
                    <flux:table.cell>{{ $ocorrencia->pagina }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$ocorrencia->tipo->badgeColor()">{{ $ocorrencia->tipo->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="min-w-72 whitespace-normal">
                        <span class="line-clamp-2 max-w-xl">{{ \Illuminate\Support\Str::limit($ocorrencia->trecho, 160) }}</span>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if (filled($ocorrencia->pdf_url))
                            <x-ui.action-button icon="document-arrow-down" label="Abrir PDF" color="green" :href="$ocorrencia->pdf_url" target="_blank" />
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <x-ui.empty-state icon="document-magnifying-glass" title="Nenhuma ocorrência encontrada para este cadastro." description="Ajuste os filtros ou execute uma nova busca." />
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</x-ui.table-panel>
