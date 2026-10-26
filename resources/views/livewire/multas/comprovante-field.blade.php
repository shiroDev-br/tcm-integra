<flux:field class="animate-fade-in">
    <flux:label>Anexo de comprovante</flux:label>

    @if ($multaId !== null && $comprovanteAtual !== null && $comprovante === null)
        <flux:callout icon="paper-clip" color="green" inline>
            <flux:callout.text>Comprovante atual anexado.</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('multas.comprovante', $multaId)" target="_blank">Baixar anexo</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <flux:input.file wire:model="comprovante" accept=".pdf,.jpg,.jpeg,.png" />

    <div wire:loading.flex wire:target="comprovante" class="hidden items-center gap-2 text-sm text-navy-700 dark:text-navy-300">
        <flux:icon.loading class="size-4" />
        Enviando arquivo...
    </div>

    <flux:description>PDF, JPG ou PNG de até 10 MB. Envie após o pagamento.</flux:description>
    <flux:error name="comprovante" />
</flux:field>
