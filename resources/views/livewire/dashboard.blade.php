<div class="flex flex-col gap-6">
    <x-ui.page-header title="Dashboard" subtitle="Visão geral do monitoramento do Diário Oficial" icon="squares-2x2">
        <x-slot:actions>
            <flux:button icon="arrow-path" variant="ghost" wire:click="$refresh">Atualizar</flux:button>
            <flux:button icon="magnifying-glass" variant="primary" :href="route('buscas.index')" wire:navigate>Nova busca</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="relative grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Termos ativos" :value="$totalTermosAtivos" icon="tag" hint="Municípios e empresas" :href="route('termos.index')" />
        <x-ui.stat-card label="Ocorrências" :value="$totalOcorrencias" icon="document-text" color="sky" hint="Encontradas nas coletas" :href="route('ocorrencias.index')" />
        <x-ui.stat-card label="Processos" :value="$totalProcessos" icon="folder-open" color="violet" hint="Citados no Diário" :href="route('processos.index')" />
        <x-ui.stat-card label="Multas pendentes" :value="$multasPendentes" icon="banknotes" :color="$multasPendentes > 0 ? 'red' : 'green'" hint="Aguardando pagamento" :href="route('multas.index', ['situacao' => 'pendente'])" />

        <x-ui.loading-overlay target="$refresh" label="Atualizando indicadores..." class="rounded-2xl" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.panel heading="Última coleta" icon="clock">
            @if ($ultimaColeta)
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-2xl font-semibold tracking-tight text-zinc-900 tabular-nums dark:text-white">@dataHora($ultimaColeta->created_at)</div>
                            <flux:text size="sm">{{ $ultimaColeta->created_at->diffForHumans() }}</flux:text>
                        </div>
                        <x-badges.search-status :status="$ultimaColeta->status" />
                    </div>

                    <dl class="grid grid-cols-2 gap-3">
                        <x-ui.detail-item label="Período">@periodo($ultimaColeta->periodo_inicio, $ultimaColeta->periodo_fim)</x-ui.detail-item>
                        <x-ui.detail-item label="Termos">{{ $ultimaColeta->termos }}</x-ui.detail-item>
                    </dl>
                </div>
            @else
                <x-ui.empty-state icon="clock" title="Nenhuma coleta registrada" description="Execute a primeira busca para começar o monitoramento." class="py-6" />
            @endif
        </x-ui.panel>

        <x-ui.panel heading="Últimas buscas" icon="queue-list" class="lg:col-span-2">
            <x-slot:actions>
                <flux:button size="sm" variant="ghost" icon:trailing="arrow-right" :href="route('buscas.index')" wire:navigate>Ver histórico</flux:button>
            </x-slot:actions>

            @if ($ultimasBuscas->isNotEmpty())
                <ul class="-my-3 divide-y divide-zinc-200/80 dark:divide-white/10">
                    @foreach ($ultimasBuscas as $busca)
                        <x-search-run-item :busca="$busca" wire:key="ultima-busca-{{ $busca->id }}" />
                    @endforeach
                </ul>
            @else
                <x-ui.empty-state icon="magnifying-glass" title="Sem histórico" description="As coletas executadas aparecerão aqui." class="py-6" />
            @endif
        </x-ui.panel>
    </div>
</div>
