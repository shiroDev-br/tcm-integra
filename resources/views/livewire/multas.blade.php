@use('App\Enums\MultaStatus')

@php
    $filtros = 'busca,filtroTermo,filtroSituacao';
@endphp

<div class="flex flex-col gap-6">
    <x-ui.page-header title="Multas" subtitle="Cadastro e acompanhamento de multas manuais" icon="banknotes">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="criar">Nova multa</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :target="$filtros">
        <flux:field class="flex-1">
            <flux:label>Busca</flux:label>
            <flux:input wire:model.live.debounce.400ms="busca" icon="magnifying-glass" placeholder="Processo, gestor ou município" clearable />
        </flux:field>

        <flux:field class="sm:w-56">
            <flux:label>Município</flux:label>
            <flux:select wire:model.live="filtroTermo">
                <flux:select.option value="">Todos os municípios</flux:select.option>
                @foreach ($this->municipios as $municipio)
                    <flux:select.option wire:key="filtro-termo-{{ $municipio->id }}" :value="$municipio->id">{{ $municipio->termo }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field class="sm:w-44">
            <flux:label>Situação</flux:label>
            <flux:select wire:model.live="filtroSituacao">
                <flux:select.option value="">Todas</flux:select.option>
                @foreach (MultaStatus::cases() as $status)
                    <flux:select.option wire:key="filtro-situacao-{{ $status->value }}" :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    </x-ui.filter-bar>

    <x-ui.table-panel :target="$filtros.',excluir'" label="Buscando multas...">
        <flux:table :paginate="$this->multas">
            <flux:table.columns>
                <flux:table.column>Município</flux:table.column>
                <flux:table.column>Gestor</flux:table.column>
                <flux:table.column>Processo</flux:table.column>
                <flux:table.column>Data da multa</flux:table.column>
                <flux:table.column>Vencimento</flux:table.column>
                <flux:table.column align="end">Valor</flux:table.column>
                <flux:table.column>Situação</flux:table.column>
                <flux:table.column align="end">Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->multas as $multa)
                    <flux:table.row :key="$multa->id">
                        <flux:table.cell variant="strong">{{ $multa->termo?->termo }}</flux:table.cell>
                        <flux:table.cell class="max-w-56 truncate">{{ $multa->gestor }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $multa->processo_numero }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">@data($multa->data_multa)</flux:table.cell>
                        <flux:table.cell class="tabular-nums">
                            <span @class(['font-medium text-red-600 dark:text-red-400' => $multa->isVencida()])>@data($multa->data_vencimento)</span>
                            @if ($multa->isVencida())
                                <div class="text-xs text-red-500 dark:text-red-400">Vencida</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" variant="strong" class="tabular-nums">
                            R$ {{ number_format((float) $multa->valor, 2, ',', '.') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$multa->situacao->badgeColor()">{{ $multa->situacao->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                @if ($multa->comprovante !== null)
                                    <x-ui.action-button icon="paper-clip" label="Baixar comprovante" color="green" :href="route('multas.comprovante', $multa)" target="_blank" />
                                @endif

                                <x-ui.action-button icon="pencil-square" label="Editar multa" color="amber" wire:click="editar({{ $multa->id }})" />
                                <x-ui.action-button icon="trash" label="Excluir multa" color="red" wire:click="confirmarExclusao({{ $multa->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">
                            <x-ui.empty-state icon="banknotes" title="Nenhuma multa encontrada." description="Ajuste os filtros ou cadastre uma nova multa.">
                                <flux:button size="sm" icon="plus" wire:click="criar">Nova multa</flux:button>
                            </x-ui.empty-state>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.table-panel>

    @include('livewire.multas.form-modal')

    <x-ui.confirm-modal
        model="exclusaoAberta"
        action="excluir"
        title="Excluir multa"
        description="Esta ação remove a multa e o comprovante anexado, e não pode ser desfeita."
    />
</div>
