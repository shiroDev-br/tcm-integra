@props([
    'heading' => null,
    'subheading' => null,
    'icon' => null,
])

<section {{ $attributes->class('relative surface-card') }}>
    @if ($heading)
        <x-ui.panel-header :heading="$heading" :subheading="$subheading" :icon="$icon">
            {{ $actions ?? '' }}
        </x-ui.panel-header>
    @endif

    <div class="p-5">
        {{ $slot }}
    </div>
</section>
