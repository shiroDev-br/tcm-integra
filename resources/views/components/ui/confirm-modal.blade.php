{{-- Modal de confirmação de ação destrutiva. --}}
@props([
    'model',
    'title',
    'action',
    'description' => null,
    'confirmLabel' => 'Excluir',
    'icon' => 'exclamation-triangle',
])

<flux:modal wire:model="{{ $model }}" focusable {{ $attributes->class('w-full md:max-w-md') }}>
    <form wire:submit="{{ $action }}" class="space-y-6">
        <div class="flex items-start gap-4">
            <x-ui.icon-chip :icon="$icon" color="red" size="lg" halo />

            <div class="space-y-1">
                <flux:heading size="lg">{{ $title }}</flux:heading>

                @if ($description)
                    <flux:text>{{ $description }}</flux:text>
                @endif

                {{ $slot }}
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:modal.close>
                <flux:button variant="ghost" type="button" class="w-full sm:w-auto">Cancelar</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" icon="trash" class="w-full sm:w-auto">
                {{ $confirmLabel }}
            </flux:button>
        </div>
    </form>
</flux:modal>
