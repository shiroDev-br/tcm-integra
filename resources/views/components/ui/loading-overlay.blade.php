{{-- Camada translúcida com indicador exibida sobre o conteúdo enquanto uma requisição Livewire está em andamento. --}}
@props([
    'target' => null,
    'label' => 'Carregando...',
])

<div
    wire:loading.delay.flex
    @if ($target) wire:target="{{ $target }}" @endif
    {{ $attributes->class('absolute inset-0 z-10 hidden items-start justify-center rounded-[inherit] bg-white/60 pt-16 backdrop-blur-[2px] dark:bg-zinc-900/60') }}
>
    <div role="status" class="flex animate-fade-in items-center gap-2.5 rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-navy-800 shadow-lg dark:border-white/10 dark:bg-zinc-800 dark:text-navy-200">
        <flux:icon.loading class="size-4" />
        {{ $label }}
    </div>
</div>
