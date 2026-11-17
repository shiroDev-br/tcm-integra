<div>
    <flux:modal wire:model="aberto" wire:close="fechar" focusable class="w-full md:w-2xl">
        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3 pe-8">
                <div class="flex min-w-0 items-start gap-3">
                    <x-ui.icon-chip icon="folder-open" />

                    <div class="min-w-0">
                        <flux:heading size="lg">Processo {{ $detalhe?->numero }}</flux:heading>

                        @if ($detalhe !== null)
                            <x-badges.active :active="$detalhe->encontrado" on="Localizado" off="Não localizado" class="mt-1" />
                        @endif
                    </div>
                </div>

                @if ($detalhe !== null)
                    <flux:button size="sm" icon="arrow-path" wire:click="buscarAndamento" wire:loading.attr="disabled" wire:target="buscarAndamento">
                        <span wire:loading.remove wire:target="buscarAndamento">Buscar andamento novamente</span>
                        <span wire:loading wire:target="buscarAndamento">Consultando o TCM...</span>
                    </flux:button>
                @endif
            </div>

            @includeWhen($detalhe !== null, 'livewire.processos.detail-body')

            <div class="flex justify-end border-t border-zinc-200/80 pt-4 dark:border-white/10">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">Fechar</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
