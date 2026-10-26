@props([
    'label',
])

<div {{ $attributes->class('space-y-1 rounded-xl bg-zinc-50 px-4 py-3 dark:bg-white/5') }}>
    <dt class="text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $label }}</dt>
    <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $slot->isEmpty() ? '—' : $slot }}</dd>
</div>
