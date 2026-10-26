<div class="flex flex-col gap-6">
    <x-ui.page-header title="Usuários" subtitle="Contas de acesso à API do painel (papéis admin/superadmin)" icon="users">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="criar">Nova conta</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table-panel target="alternarAtivo,excluir,salvar" label="Atualizando contas...">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>ID</flux:table.column>
                <flux:table.column>Username</flux:table.column>
                <flux:table.column>Papel</flux:table.column>
                <flux:table.column>Situação</flux:table.column>
                <flux:table.column>Criado em</flux:table.column>
                <flux:table.column align="end">Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->usuarios as $usuario)
                    <flux:table.row :key="$usuario->id">
                        <flux:table.cell class="text-zinc-500 tabular-nums dark:text-zinc-400">#{{ $usuario->id }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-2">
                                <x-ui.avatar size="xs" :name="$usuario->username" />
                                {{ $usuario->username }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$usuario->role->badgeColor()">{{ $usuario->role->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell><x-badges.active :active="(bool) $usuario->ativo" on="Ativa" off="Desativada" /></flux:table.cell>
                        <flux:table.cell class="tabular-nums">@dataHora($usuario->criado_em)</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.action-button icon="key" label="Redefinir senha" color="sky" wire:click="abrirSenha({{ $usuario->id }})" />

                                @if ($usuario->ativo)
                                    <x-ui.action-button icon="no-symbol" label="Desativar conta" color="amber" wire:click="alternarAtivo({{ $usuario->id }})" />
                                @else
                                    <x-ui.action-button icon="check-circle" label="Ativar conta" color="green" wire:click="alternarAtivo({{ $usuario->id }})" />
                                @endif

                                <x-ui.action-button icon="trash" label="Excluir conta" color="red" wire:click="confirmarExclusao({{ $usuario->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <x-ui.empty-state icon="users" title="Nenhuma conta cadastrada." description="Crie a primeira conta de acesso ao painel.">
                                <flux:button size="sm" icon="plus" wire:click="criar">Nova conta</flux:button>
                            </x-ui.empty-state>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.table-panel>

    @include('livewire.usuarios.form-modals')

    <x-ui.confirm-modal
        model="exclusaoAberta"
        action="excluir"
        title="Excluir conta"
        description="Esta ação remove a conta de acesso e não pode ser desfeita. Superadmins não podem ser excluídos."
    />
</div>
