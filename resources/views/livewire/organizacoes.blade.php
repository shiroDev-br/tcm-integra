@use('App\Enums\OrganizacaoStatus')

<div class="flex flex-col gap-6" @if ($this->emAndamento) wire:poll.5s @endif>
    <x-ui.page-header title="Organizador" subtitle="Organiza os arquivos da prestação de contas no padrão de entrega do TCM" icon="folder-open">
        <x-slot:actions>
            <flux:button variant="primary" icon="arrow-up-tray" wire:click="novo">Organizar arquivos</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.panel heading="O que o organizador faz" icon="sparkles">
        <ul class="grid gap-2 text-sm text-zinc-600 sm:grid-cols-2 dark:text-zinc-300">
            <li>Distribui os PDFs nas pastas do modelo (Processo Pagamento, Extra, Folha, Licitação, SIGA...).</li>
            <li>Reconhece cada página e ordena o processo: Ordem de Pagamento, Liquidação, Empenho, Sub-empenho, NF/Folha/Diárias e Comprovante.</li>
            <li>Unifica páginas do mesmo processo vindas de arquivos diferentes e nomeia como "PP &lt;número&gt; &lt;FORNECEDOR&gt;".</li>
            <li>Aplica OCR (PDF pesquisável), comprime e divide em partes para respeitar {{ config('organizador.limite_arquivo_kb') }}KB por arquivo e {{ config('organizador.limite_pagina_kb') }}KB por página.</li>
        </ul>
    </x-ui.panel>

    <x-ui.table-panel target="excluir" label="Atualizando...">
        <flux:table :paginate="$this->organizacoes">
            <flux:table.columns>
                <flux:table.column>Entidade</flux:table.column>
                <flux:table.column>Enviado por</flux:table.column>
                <flux:table.column>Enviado em</flux:table.column>
                <flux:table.column>Situação</flux:table.column>
                <flux:table.column align="end">Arquivos</flux:table.column>
                <flux:table.column align="end">Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->organizacoes as $organizacao)
                    <flux:table.row :key="$organizacao->id">
                        <flux:table.cell variant="strong" class="max-w-72 truncate">{{ $organizacao->entidade ?? 'Identificando...' }}</flux:table.cell>
                        <flux:table.cell>{{ $organizacao->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">@dataHora($organizacao->created_at)</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" :color="$organizacao->status->badgeColor()">{{ $organizacao->status->label() }}</flux:badge>
                                @if ($organizacao->status->isEmAndamento())
                                    <flux:icon.loading class="size-4 text-zinc-400" />
                                @endif
                                @if (count($organizacao->avisos ?? []) > 0)
                                    <flux:tooltip content="{{ count($organizacao->avisos) }} aviso(s) gerais">
                                        <flux:icon.exclamation-triangle variant="micro" class="text-amber-500" />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $organizacao->arquivos_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                @if ($organizacao->status === OrganizacaoStatus::Concluida)
                                    <x-ui.action-button icon="arrow-down-tray" label="Baixar zip organizado" color="green" :href="route('organizacoes.download', $organizacao)" />
                                @endif

                                @unless ($organizacao->status->isEmAndamento())
                                    <x-ui.action-button icon="eye" label="Ver detalhes" color="sky" wire:click="verDetalhes({{ $organizacao->id }})" />
                                    <x-ui.action-button icon="trash" label="Excluir" color="red" wire:click="confirmarExclusao({{ $organizacao->id }})" />
                                @endunless
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <x-ui.empty-state icon="folder-open" title="Nenhuma organização ainda." description="Envie o zip (ou os PDFs) da prestação de contas para receber tudo organizado.">
                                <flux:button size="sm" icon="arrow-up-tray" wire:click="novo">Organizar arquivos</flux:button>
                            </x-ui.empty-state>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.table-panel>

    @include('livewire.organizacoes.form-modal')
    @include('livewire.organizacoes.detalhe-modal')

    <x-ui.confirm-modal
        model="exclusaoAberta"
        action="excluir"
        title="Excluir organização"
        description="Remove o registro e o zip organizado. Esta ação não pode ser desfeita."
    />
</div>
