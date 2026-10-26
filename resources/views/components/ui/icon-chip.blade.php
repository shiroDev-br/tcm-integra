{{-- Quadrado arredondado com ícone (ou círculo com `halo`); `color` define o tom (navy, solid, red, amber, green, sky, zinc, violet). --}}
@props([
    'icon',
    'color' => 'navy',
    'size' => 'md',
    'halo' => false,
])

@php
    $toneClasses = match ($color) {
        'solid' => 'bg-navy-800 text-white shadow-sm shadow-navy-900/20 dark:bg-navy-500/15 dark:text-navy-300 dark:shadow-none dark:ring-1 dark:ring-navy-400/25',
        'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        'green' => 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400',
        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
        'zinc' => 'bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400',
        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
        default => 'bg-navy-50 text-navy-700 dark:bg-navy-500/15 dark:text-navy-300',
    };

    $sizeClasses = match ($size) {
        'sm' => 'size-9 rounded-lg',
        'lg' => 'size-11 rounded-xl',
        'xl' => 'size-12 rounded-xl',
        default => 'size-10 rounded-xl',
    };
@endphp

<div {{ $attributes->class(['flex shrink-0 items-center justify-center', $toneClasses, $sizeClasses, 'rounded-full! ring-8 ring-current/5' => $halo]) }}>
    <flux:icon :icon="$icon" variant="outline" class="size-5" />
</div>
