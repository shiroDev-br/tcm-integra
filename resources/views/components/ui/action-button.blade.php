{{-- Botão de ação de linha (ícone + tooltip). A cor indica a natureza da ação: green, amber, red, sky... --}}
@props([
    'icon',
    'label',
    'color' => null,
])

<flux:tooltip :content="$label" position="top">
    <flux:button
        {{ $attributes }}
        :icon="$icon"
        variant="ghost"
        size="sm"
        :color="$color"
        :aria-label="$label"
    />
</flux:tooltip>
