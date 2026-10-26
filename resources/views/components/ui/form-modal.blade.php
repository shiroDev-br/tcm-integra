{{-- Modal com formulário: cabeçalho, corpo (slot) e rodapé com Cancelar / Salvar (com loading). --}}
@props([
    'model',
    'title',
    'action',
    'subtitle' => null,
    'icon' => null,
    'submitLabel' => 'Salvar',
    'submitIcon' => 'check',
])

<flux:modal wire:model="{{ $model }}" focusable {{ $attributes->class('w-full') }}>
    <form wire:submit="{{ $action }}" class="space-y-6">
        <div class="flex items-start gap-3">
            @if ($icon)
                <x-ui.icon-chip :icon="$icon" />
            @endif

            <div>
                <flux:heading size="lg">{{ $title }}</flux:heading>

                @if ($subtitle)
                    <flux:text class="mt-1">{{ $subtitle }}</flux:text>
                @endif
            </div>
        </div>

        <fieldset wire:loading.attr="disabled" wire:target="{{ $action }}" class="space-y-4 disabled:opacity-60">
            {{ $slot }}
        </fieldset>

        <div class="flex flex-col-reverse gap-2 border-t border-zinc-200/80 pt-4 sm:flex-row sm:justify-end dark:border-white/10">
            <flux:modal.close>
                <flux:button variant="ghost" type="button" class="w-full sm:w-auto">Cancelar</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit" :icon="$submitIcon" class="w-full sm:w-auto">
                {{ $submitLabel }}
            </flux:button>
        </div>
    </form>
</flux:modal>
