<flux:tooltip content="Alternar tema" position="bottom">
    <flux:button
        {{ $attributes }}
        x-data
        x-on:click="$flux.dark = ! $flux.dark"
        variant="subtle"
        square
        aria-label="Alternar entre tema claro e escuro"
    >
        <flux:icon.sun x-show="$flux.dark" x-cloak variant="mini" />
        <flux:icon.moon x-show="! $flux.dark" x-cloak variant="mini" />
    </flux:button>
</flux:tooltip>
