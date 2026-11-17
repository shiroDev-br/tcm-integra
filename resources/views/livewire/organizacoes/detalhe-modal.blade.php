@use('App\Enums\OrganizacaoStatus')

<flux:modal wire:model="detalheAberto" class="w-full md:w-4xl">
    @if ($detalhe = $this->detalhe)
        <div class="space-y-6">
            <div class="flex items-start gap-3">
                <x-ui.icon-chip icon="folder-open" />
                <div>
                    <flux:heading size="lg">{{ $detalhe->entidade ?? 'Organização' }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $detalhe->arquivos->count() }} arquivo(s) gerado(s)
                        @if ($detalhe->concluido_em)
                            · concluída em @dataHora($detalhe->concluido_em)
                        @endif
                    </flux:text>
                </div>
            </div>

            @if ($detalhe->status === OrganizacaoStatus::Falhou)
                <flux:callout icon="x-circle" color="red">
                    <flux:callout.heading>A organização falhou</flux:callout.heading>
                    <flux:callout.text>{{ $detalhe->erro }}</flux:callout.text>
                </flux:callout>
            @endif

            @if (count($detalhe->avisos ?? []) > 0)
                <flux:callout icon="exclamation-triangle" color="amber">
                    <flux:callout.heading>Avisos gerais</flux:callout.heading>
                    <flux:callout.text>
                        <ul class="list-disc space-y-1 ps-4">
                            @foreach ($detalhe->avisos as $aviso)
                                <li>{{ $aviso }}</li>
                            @endforeach
                        </ul>
                    </flux:callout.text>
                </flux:callout>
            @endif

            @foreach ($detalhe->arquivos->groupBy(fn ($arquivo) => $arquivo->pasta->label()) as $pasta => $arquivosDaPasta)
                <div wire:key="pasta-{{ $pasta }}" class="space-y-2">
                    <flux:heading size="sm" class="flex items-center gap-2">
                        <flux:icon.folder variant="micro" class="text-zinc-400" />
                        {{ $pasta }}
                    </flux:heading>

                    <ul class="divide-y divide-zinc-200/80 rounded-lg border border-zinc-200/80 dark:divide-white/10 dark:border-white/10">
                        @foreach ($arquivosDaPasta as $arquivo)
                            <li wire:key="arquivo-{{ $arquivo->id }}" class="px-3 py-2 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="min-w-0 truncate font-medium">{{ $arquivo->nome }}</span>
                                    <span class="shrink-0 tabular-nums text-zinc-500">{{ $arquivo->paginas }} p · {{ $arquivo->tamanhoEmKb() }} KB</span>
                                </div>

                                @foreach ($arquivo->avisos ?? [] as $aviso)
                                    <div class="mt-1 flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                                        <flux:icon.exclamation-triangle variant="micro" class="mt-px shrink-0" />
                                        <span>{{ $aviso }}</span>
                                    </div>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="flex justify-end gap-2 border-t border-zinc-200/80 pt-4 dark:border-white/10">
                <flux:modal.close>
                    <flux:button variant="ghost">Fechar</flux:button>
                </flux:modal.close>

                @if ($detalhe->status === OrganizacaoStatus::Concluida)
                    <flux:button variant="primary" icon="arrow-down-tray" :href="route('organizacoes.download', $detalhe)">Baixar zip</flux:button>
                @endif
            </div>
        </div>
    @endif
</flux:modal>
