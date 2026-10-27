@use('App\Enums\TermType')

@php
    $filtros = 'busca,filtroTipo,filtroAtivo';
@endphp

<div class="flex flex-col gap-6">
    <x-ui.page-header title="Cadastros" subtitle="Municípios e empresas monitorados nas coletas" icon="tag">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="criar">Novo cadastro</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :target="$filtros">
        <flux:field class="flex-1">
            <flux:label>Busca</flux:label>
            <flux:input wire:model.live.debounce.400ms="busca" icon="magnifying-glass" placeholder="Nome do município ou empresa" clearable />
        </flux:field>

        <flux:field class="sm:w-48">
            <flux:label>Tipo</flux:label>
            <flux:select wire:model.live="filtroTipo">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach (TermType::cases() as $tipo)
                    <flux:select.option wire:key="filtro-tipo-{{ $tipo->value }}" :value="$tipo->value">{{ $tipo->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field class="sm:w-44">
            <flux:label>Situação</flux:label>
            <flux:select wire:model.live="filtroAtivo">
                <flux:select.option :value="true">Ativos</flux:select.option>
                <flux:select.option :value="false">Todos</flux:select.option>
            </flux:select>
        </flux:field>
    </x-ui.filter-bar>

    <x-ui.table-panel :target="$filtros.',excluir'" label="Buscando cadastros...">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>ID</flux:table.column>
                <flux:table.column>Nome</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column>Variações</flux:table.column>
                <flux:table.column>Situação</flux:table.column>
                <flux:table.column align="end">Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($termos as $termo)
                    <flux:table.row :key="$termo->id">
                        <flux:table.cell class="text-zinc-500 tabular-nums dark:text-zinc-400">#{{ $termo->id }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $termo->termo }}</flux:table.cell>
                        <flux:table.cell><x-badges.term-type :tipo="$termo->tipo" /></flux:table.cell>
                        <flux:table.cell class="whitespace-normal">
                            <div class="flex max-w-md flex-wrap gap-1">
                                @forelse ($termo->listaVariacoes() as $variacao)
                                    <flux:badge size="sm" color="zinc" wire:key="var-{{ $termo->id }}-{{ $loop->index }}">{{ $variacao }}</flux:badge>
                                @empty
                                    <span class="text-zinc-400">—</span>
                                @endforelse
                            </div>
                        </flux:table.cell>
                        <flux:table.cell><x-badges.active :active="(bool) $termo->ativo" /></flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.action-button icon="pencil-square" label="Editar cadastro" color="amber" wire:click="editar({{ $termo->id }})" />
                                <x-ui.action-button icon="trash" label="Excluir cadastro" color="red" wire:click="confirmarExclusao({{ $termo->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <x-ui.empty-state icon="tag" title="Nenhum cadastro encontrado." description="Ajuste os filtros ou crie um novo cadastro.">
                                <flux:button size="sm" icon="plus" wire:click="criar">Novo cadastro</flux:button>
                            </x-ui.empty-state>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.table-panel>

    @include('livewire.cadastros.form-modal')

    <x-ui.confirm-modal
        model="exclusaoAberta"
        action="excluir"
        title="Excluir cadastro"
        description="Esta ação remove o cadastro, suas ocorrências e vínculos com processos. A tabela de processos permanece."
    />
</div>
