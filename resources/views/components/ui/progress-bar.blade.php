{{-- Barra de progresso indeterminada exibida no topo de um bloco durante requisições Livewire. --}}
@props([
    'target' => null,
])

<div
    wire:loading.delay.block
    @if ($target) wire:target="{{ $target }}" @endif
    {{ $attributes->class('absolute inset-x-0 top-0 z-20 hidden h-0.5 overflow-hidden rounded-t-[inherit] bg-navy-100 dark:bg-navy-950') }}
>
    <div class="h-full w-1/4 animate-progress rounded-full bg-navy-600 dark:bg-navy-400"></div>
</div>
