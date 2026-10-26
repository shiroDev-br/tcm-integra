@use('App\Enums\MultaStatus')

<x-ui.form-modal
    model="formAberto"
    action="salvar"
    :title="$multaId !== null ? 'Editar multa' : 'Nova multa'"
    subtitle="Informe os dados da multa aplicada ao município."
    icon="banknotes"
    class="md:w-2xl"
>
    <div class="grid gap-4 sm:grid-cols-2">
        <flux:field class="sm:col-span-2">
            <flux:label>Município</flux:label>
            <flux:select wire:model="termoId">
                <flux:select.option value="">Selecione o município</flux:select.option>
                @foreach ($this->municipios as $municipio)
                    <flux:select.option wire:key="form-termo-{{ $municipio->id }}" :value="$municipio->id">{{ $municipio->termo }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="termoId" />
        </flux:field>

        <flux:field>
            <flux:label>Gestor</flux:label>
            <flux:input wire:model="gestor" placeholder="Nome do gestor responsável" />
            <flux:error name="gestor" />
        </flux:field>

        <flux:field>
            <flux:label>Número do processo</flux:label>
            <flux:input wire:model="processoNumero" placeholder="Ex.: 09768" />
            <flux:error name="processoNumero" />
        </flux:field>

        <flux:field>
            <flux:label>Data da multa</flux:label>
            <flux:input type="date" wire:model="dataMulta" />
            <flux:error name="dataMulta" />
        </flux:field>

        <flux:field>
            <flux:label>Data do vencimento</flux:label>
            <flux:input type="date" wire:model="dataVencimento" />
            <flux:error name="dataVencimento" />
        </flux:field>

        <flux:field>
            <flux:label>Valor (R$)</flux:label>
            <flux:input type="number" step="0.01" min="0" wire:model="valor" placeholder="0,00" />
            <flux:error name="valor" />
        </flux:field>

        <flux:field>
            <flux:label>Situação</flux:label>
            <flux:radio.group wire:model.live="situacao" variant="segmented">
                @foreach (MultaStatus::cases() as $status)
                    <flux:radio wire:key="form-situacao-{{ $status->value }}" :value="$status->value">{{ $status->label() }}</flux:radio>
                @endforeach
            </flux:radio.group>
            <flux:error name="situacao" />
        </flux:field>
    </div>

    @includeWhen($situacao === MultaStatus::Paga->value, 'livewire.multas.comprovante-field')
</x-ui.form-modal>
