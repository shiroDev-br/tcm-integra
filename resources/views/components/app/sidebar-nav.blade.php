<flux:sidebar.nav {{ $attributes }}>
    <flux:sidebar.group heading="Visão geral" class="grid">
        <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
            {{ __('Dashboard') }}
        </flux:sidebar.item>

        <flux:sidebar.item icon="banknotes" :href="route('multas.index')" :current="request()->routeIs('multas.*')" wire:navigate>
            {{ __('Multas') }}
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Monitoramento" class="grid">
        <flux:sidebar.item icon="magnifying-glass" :href="route('buscas.index')" :current="request()->routeIs('buscas.index')" wire:navigate>
            {{ __('Buscas') }}
        </flux:sidebar.item>

        <flux:sidebar.item icon="document-text" :href="route('ocorrencias.index')" :current="request()->routeIs('ocorrencias.index')" wire:navigate>
            {{ __('Ocorrências') }}
        </flux:sidebar.item>

        <flux:sidebar.item icon="folder-open" :href="route('processos.index')" :current="request()->routeIs('processos.index')" wire:navigate>
            {{ __('Processos') }}
        </flux:sidebar.item>

        <flux:sidebar.item icon="newspaper" :href="route('edicoes.index')" :current="request()->routeIs('edicoes.index')" wire:navigate>
            {{ __('Edições') }}
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Administração" class="grid">
        <flux:sidebar.item icon="tag" :href="route('termos.index')" :current="request()->routeIs('termos.index')" wire:navigate>
            {{ __('Termos') }}
        </flux:sidebar.item>

        @can('manage-panel-accounts')
            <flux:sidebar.item icon="users" :href="route('usuarios.index')" :current="request()->routeIs('usuarios.index')" wire:navigate>
                {{ __('Usuários') }}
            </flux:sidebar.item>
        @endcan
    </flux:sidebar.group>
</flux:sidebar.nav>
