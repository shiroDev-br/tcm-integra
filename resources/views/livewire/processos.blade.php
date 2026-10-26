<div class="flex flex-col gap-6">
    @if ($termoAtual === null)
        <x-ui.page-header title="Processos" subtitle="Selecione um município ou termo para ver os processos citados" icon="folder-open" />

        <x-term-cards
            :termos="$this->termos"
            count-attribute="processos_count"
            singular="processo encontrado"
            plural="processos encontrados"
            empty-description="Cadastre municípios ou empresas para acompanhar os processos."
        />
    @else
        <x-term-header :termo="$termoAtual" subtitle="Processos citados neste termo" />

        @include('livewire.processos.table')
    @endif

    @include('livewire.processos.detail-modal')
</div>
