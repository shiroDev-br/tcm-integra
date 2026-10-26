{{-- Conteúdo do menu do usuário autenticado (perfil, configurações, tema e saída). --}}
<flux:menu {{ $attributes->class('min-w-56') }}>
    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
        <x-ui.avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

        <div class="grid flex-1 text-start text-sm leading-tight">
            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
        </div>
    </div>

    <flux:menu.separator />

    <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
        {{ __('Settings') }}
    </flux:menu.item>

    <flux:menu.item as="button" type="button" icon="moon" x-data x-on:click="$flux.dark = ! $flux.dark" class="w-full cursor-pointer">
        Alternar tema
    </flux:menu.item>

    <flux:menu.separator />

    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <flux:menu.item
            as="button"
            type="submit"
            icon="arrow-right-start-on-rectangle"
            variant="danger"
            class="w-full cursor-pointer"
            data-test="logout-button"
        >
            {{ __('Log out') }}
        </flux:menu.item>
    </form>
</flux:menu>
