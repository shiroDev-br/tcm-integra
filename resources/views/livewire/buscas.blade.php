<div class="flex flex-col gap-6">
    <x-ui.page-header title="Buscas" subtitle="Execute uma coleta no Diário Oficial do TCM-BA" icon="magnifying-glass" />

    <x-ui.panel heading="Período da coleta" subheading="Escolha um período pronto ou informe datas específicas" icon="calendar-days">
        <form wire:submit.prevent="buscar" x-on:submit="$dispatch('coleta-iniciada')" class="grid gap-6">
            <fieldset wire:loading.attr="disabled" wire:target="buscar" class="grid gap-6 disabled:opacity-60 lg:grid-cols-2">
                <flux:field>
                    <flux:label>Períodos prontos</flux:label>
                    <flux:radio.group wire:model.live="periodo" variant="segmented">
                        <flux:radio value="hoje" label="Hoje" />
                        <flux:radio value="7" label="Últimos 7 dias" />
                        <flux:radio value="30" label="Últimos 30 dias" />
                    </flux:radio.group>
                    <flux:error name="periodo" />
                </flux:field>

                <flux:field>
                    <flux:label>Período personalizado</flux:label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <flux:input type="date" wire:model.live="inicio" aria-label="Data inicial" />
                        <flux:input type="date" wire:model.live="fim" aria-label="Data final" />
                    </div>
                    <flux:error name="inicio" />
                    <flux:error name="fim" />
                </flux:field>
            </fieldset>

            <div class="flex flex-col gap-3 border-t border-zinc-200/80 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                @if (filled($inicio) || filled($fim))
                    <flux:callout icon="information-circle" color="sky" class="sm:max-w-md" inline>
                        <flux:callout.text>As datas informadas substituem o período pronto.</flux:callout.text>
                        <x-slot name="actions">
                            <flux:button size="sm" variant="ghost" x-on:click="$wire.$set('inicio', null); $wire.$set('fim', null)">Limpar datas</flux:button>
                        </x-slot>
                    </flux:callout>
                @else
                    <flux:text size="sm">Preencha as datas para ignorar o período pronto.</flux:text>
                @endif

                <flux:button variant="primary" icon="magnifying-glass" type="submit" class="w-full sm:w-auto">
                    Buscar agora
                </flux:button>
            </div>
        </form>
    </x-ui.panel>

    @include('livewire.buscas.collecting')

    <section class="flex flex-col gap-3">
        <flux:heading size="lg">Histórico de buscas</flux:heading>

        <x-ui.table-panel target="buscar" label="Atualizando histórico...">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Período</flux:table.column>
                    <flux:table.column>Termos</flux:table.column>
                    <flux:table.column>Duração</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Quando</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->historico as $busca)
                        <flux:table.row :key="$busca->id">
                            <flux:table.cell variant="strong">@periodo($busca->periodo_inicio, $busca->periodo_fim)</flux:table.cell>
                            <flux:table.cell>{{ $busca->termos }}</flux:table.cell>
                            <flux:table.cell>{{ $busca->duracao_ms !== null ? number_format($busca->duracao_ms / 1000, 1, ',', '.').' s' : '—' }}</flux:table.cell>
                            <flux:table.cell><x-badges.search-status :status="$busca->status" /></flux:table.cell>
                            <flux:table.cell>
                                <div class="tabular-nums">@dataHora($busca->created_at)</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $busca->created_at?->diffForHumans() }}</div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">
                                <x-ui.empty-state icon="magnifying-glass" title="Nenhuma busca registrada" description="Use o formulário acima para iniciar uma nova coleta." />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-ui.table-panel>
    </section>
</div>
