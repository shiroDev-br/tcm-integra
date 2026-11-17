<x-ui.form-modal
    model="formAberto"
    action="enviar"
    title="Organizar arquivos"
    subtitle="Envie o zip da prestação de contas ou os PDFs soltos. O processamento roda em segundo plano."
    icon="arrow-up-tray"
    submit-label="Organizar"
    submit-icon="sparkles"
    class="md:w-2xl"
>
    <flux:field>
        <flux:label badge="Opcional">Entidade</flux:label>
        <flux:input wire:model="entidade" placeholder="Ex.: CM de Bom Jesus da Serra" />
        <flux:description>Nome da pasta raiz do zip. Vazio: usa a pasta raiz ou o nome do zip enviado.</flux:description>
        <flux:error name="entidade" />
    </flux:field>

    <flux:field>
        <flux:label>Arquivos</flux:label>
        <flux:input.file wire:model="arquivos" multiple accept=".zip,.pdf" />

        <div wire:loading.flex wire:target="arquivos" class="hidden items-center gap-2 text-sm text-navy-700 dark:text-navy-300">
            <flux:icon.loading class="size-4" />
            Enviando arquivos...
        </div>

        <flux:description>Zips e PDFs de até {{ intdiv((int) config('organizador.upload_max_kb'), 1024) }} MB cada.</flux:description>
        <flux:error name="arquivos" />
        <flux:error name="arquivos.*" />
    </flux:field>
</x-ui.form-modal>
