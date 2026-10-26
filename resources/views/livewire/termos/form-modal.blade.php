@use('App\Enums\TermType')

<x-ui.form-modal
    model="formAberto"
    action="salvar"
    :title="$editandoId !== null ? 'Editar termo' : 'Novo termo'"
    subtitle="Termos ativos são pesquisados em todas as coletas."
    icon="tag"
    class="md:w-2xl"
>
    <flux:field>
        <flux:label>Termo</flux:label>
        <flux:input wire:model="editandoTermo" placeholder="Ex.: Maiquinique" />
        <flux:error name="editandoTermo" />
    </flux:field>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:field>
            <flux:label>Tipo</flux:label>
            <flux:select wire:model="editandoTipo">
                @foreach (TermType::cases() as $tipo)
                    <flux:select.option wire:key="form-tipo-{{ $tipo->value }}" :value="$tipo->value">{{ $tipo->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="editandoTipo" />
        </flux:field>

        <flux:field>
            <flux:label>Situação</flux:label>
            <flux:select wire:model="editandoAtivo">
                <flux:select.option :value="true">Ativo</flux:select.option>
                <flux:select.option :value="false">Inativo</flux:select.option>
            </flux:select>
            <flux:error name="editandoAtivo" />
        </flux:field>
    </div>

    <flux:field>
        <flux:label>Variações</flux:label>
        <flux:textarea wire:model="editandoVariacoes" rows="4" placeholder="Uma variação por linha (máx. 10)" />
        <flux:description>Uma variação por linha. Termos com espaço são buscados com aspas automaticamente.</flux:description>
        <flux:error name="editandoVariacoes" />
    </flux:field>
</x-ui.form-modal>
