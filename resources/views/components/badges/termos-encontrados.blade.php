{{-- Cadastros encontrados em uma coleta, cada um levando às suas ocorrências e processos. --}}
@props([
    'busca',
    'vazio' => 'Nenhum cadastro encontrado',
])

@php
    $encontrados = $busca->listaTermosEncontrados();
@endphp

@if ($busca->termos_encontrados === null)
    <span {{ $attributes->class('text-zinc-500 dark:text-zinc-400') }}>—</span>
@elseif ($encontrados === [])
    <span {{ $attributes->class('text-sm text-zinc-500 dark:text-zinc-400') }}>{{ $vazio }}</span>
@else
    <div {{ $attributes->class('flex flex-wrap gap-1.5') }}>
        @foreach ($encontrados as $encontrado)
            <a
                wire:key="encontrado-{{ $busca->id }}-{{ $encontrado['termo_id'] }}"
                href="{{ route('ocorrencias.index', ['termo' => $encontrado['termo_id']]) }}"
                wire:navigate
                class="rounded-md transition hover:opacity-80 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-hidden"
                title="{{ $encontrado['ocorrencias'] }} {{ $encontrado['ocorrencias'] === 1 ? 'ocorrência' : 'ocorrências' }} · {{ $encontrado['processos'] }} {{ $encontrado['processos'] === 1 ? 'processo' : 'processos' }}"
            >
                <flux:badge size="sm" color="sky" icon="tag">{{ $encontrado['termo'] }} ({{ $encontrado['ocorrencias'] }})</flux:badge>
            </a>
        @endforeach
    </div>
@endif
