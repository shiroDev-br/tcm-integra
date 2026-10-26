<x-ui.form-modal
    model="formAberto"
    action="salvar"
    title="Nova conta"
    subtitle="Novas contas são criadas sempre com o papel admin."
    icon="user-plus"
    class="md:w-lg"
>
    <flux:field>
        <flux:label>Username</flux:label>
        <flux:input wire:model="username" icon="user" placeholder="letras, dígitos, ponto, hífen e sublinhado" />
        <flux:error name="username" />
    </flux:field>

    <flux:field>
        <flux:label>Senha</flux:label>
        <flux:input type="password" wire:model="password" icon="lock-closed" placeholder="Mínimo de 8 caracteres" viewable />
        <flux:error name="password" />
    </flux:field>
</x-ui.form-modal>

<x-ui.form-modal
    model="senhaAberta"
    action="salvarSenha"
    title="Redefinir senha"
    subtitle="A nova senha passa a valer imediatamente."
    icon="key"
    class="md:w-lg"
>
    <flux:field>
        <flux:label>Nova senha</flux:label>
        <flux:input type="password" wire:model="senhaNova" icon="lock-closed" placeholder="Mínimo de 8 caracteres" viewable />
        <flux:error name="senhaNova" />
    </flux:field>
</x-ui.form-modal>
