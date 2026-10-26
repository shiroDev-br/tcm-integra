<flux:header {{ $attributes->class('sticky top-0 z-30 border-b border-zinc-200/80 bg-white/90 backdrop-blur lg:hidden dark:border-white/10 dark:bg-zinc-900/90') }}>
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" aria-label="Abrir menu" />

    <x-app-logo href="{{ route('dashboard') }}" wire:navigate class="ms-2" />

    <flux:spacer />

    <x-app.theme-toggle />

    <flux:dropdown position="top" align="end">
        <flux:profile
            :initials="auth()->user()->initials()"
            icon-trailing="chevron-down"
        />

        <x-app.user-menu />
    </flux:dropdown>
</flux:header>
