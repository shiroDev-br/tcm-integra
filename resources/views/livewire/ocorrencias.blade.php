<div class="flex flex-col gap-6">
    @if ($termoAtual === null)
        <x-ui.page-header title="Ocorrências" subtitle="Selecione um município ou termo para ver as ocorrências encontradas" icon="document-text" />

        <x-term-cards
            :termos="$this->termos"
            count-attribute="ocorrencias_count"
            singular="ocorrência encontrada"
            plural="ocorrências encontradas"
            empty-description="Cadastre municípios ou empresas para acompanhar as ocorrências."
        />
    @else
        <x-term-header :termo="$termoAtual" subtitle="Ocorrências encontradas neste termo" />

        @include('livewire.ocorrencias.table')
    @endif
</div>
