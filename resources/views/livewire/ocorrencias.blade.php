@use('App\Livewire\Ocorrencias\Index')

<div class="flex flex-col gap-6">
    @if ($termoAtual === null)
        <x-ui.page-header title="Ocorrências e processos" subtitle="Selecione um município ou cadastro para ver as ocorrências e os processos citados" icon="document-text" />

        @if ($termosNovos->isNotEmpty())
            <section class="flex flex-col gap-3 rounded-2xl border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-400/30 dark:bg-sky-400/5" aria-labelledby="encontrados-hoje">
                <div class="flex items-center gap-2">
                    <flux:icon.bell-alert class="size-5 text-sky-600 dark:text-sky-400" />
                    <flux:heading id="encontrados-hoje" size="lg">Encontrados hoje!</flux:heading>
                    <flux:badge size="sm" color="sky">{{ $termosNovos->count() }}</flux:badge>
                </div>
                <flux:text size="sm">Cadastros que a busca de hoje encontrou. Depois de abertos, voltam para a lista normal.</flux:text>

                @include('livewire.ocorrencias.cards', ['termos' => $termosNovos, 'novo' => true])
            </section>
        @endif

        @if ($termosNovos->isEmpty() || $termosDemais->isNotEmpty())
            @include('livewire.ocorrencias.cards', ['termos' => $termosDemais, 'novo' => false])
        @endif
    @else
        <x-term-header :termo="$termoAtual" subtitle="Ocorrências e processos citados neste cadastro" />

        <flux:radio.group wire:model.live="aba" variant="segmented" class="w-full sm:w-fit" aria-label="Exibir">
            <flux:radio :value="Index::ABA_OCORRENCIAS" label="Ocorrências" icon="document-text" />
            <flux:radio :value="Index::ABA_PROCESSOS" label="Processos" icon="folder-open" />
        </flux:radio.group>

        @if ($aba === Index::ABA_PROCESSOS)
            @include('livewire.ocorrencias.processos-table')
        @else
            @include('livewire.ocorrencias.ocorrencias-table')
        @endif
    @endif

    <livewire:processos.detalhe />
</div>
