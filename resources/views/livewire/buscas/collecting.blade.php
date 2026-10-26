{{-- Feedback exibido enquanto a coleta no Diário Oficial está em execução. --}}
<div
    wire:loading.flex
    wire:target="buscar"
    role="status"
    aria-live="polite"
    class="relative hidden animate-fade-in flex-col items-center gap-5 overflow-hidden rounded-2xl border border-navy-200 bg-linear-to-br from-navy-50 to-white px-6 py-10 text-center shadow-xs dark:border-navy-500/30 dark:from-navy-950 dark:to-zinc-900"
    x-data="{
        etapas: [
            'Conectando ao Diário Oficial do TCM-BA...',
            'Varrendo as edições do período...',
            'Identificando ocorrências dos termos...',
            'Consultando a situação dos processos...',
            'Consolidando os resultados...',
        ],
        atual: 0,
        timer: null,
        iniciar() {
            this.atual = 0;
            clearInterval(this.timer);
            this.timer = setInterval(() => this.atual = Math.min(this.atual + 1, this.etapas.length - 1), 2500);
        },
    }"
    x-init="iniciar()"
    x-on:coleta-iniciada.window="iniciar()"
>
    <x-ui.progress-bar target="buscar" class="h-1!" />

    <div class="relative flex size-16 items-center justify-center">
        <span class="absolute inset-0 animate-ping rounded-full bg-navy-400/30"></span>
        <span class="relative flex size-16 items-center justify-center rounded-full bg-navy-800 text-white shadow-lg shadow-navy-900/30 dark:bg-navy-500">
            <flux:icon.magnifying-glass variant="outline" class="size-7" />
        </span>
    </div>

    <div class="space-y-1">
        <flux:heading size="lg">Coleta em andamento</flux:heading>
        <flux:text x-text="etapas[atual]" class="min-h-5">Conectando ao Diário Oficial do TCM-BA...</flux:text>
    </div>

    <ol class="flex items-center gap-1.5" aria-hidden="true">
        <template x-for="(etapa, indice) in etapas" :key="indice">
            <li
                class="h-1.5 rounded-full transition-all duration-500"
                :class="indice <= atual ? 'w-6 bg-navy-700 dark:bg-navy-400' : 'w-1.5 bg-navy-200 dark:bg-navy-800'"
            ></li>
        </template>
    </ol>

    <flux:text size="sm">Isso pode levar alguns instantes. Não feche esta página.</flux:text>
</div>
