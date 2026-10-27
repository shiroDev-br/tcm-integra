<x-ui.panel heading="Coleta automática" subheading="Varre o Diário Oficial do dia atual, todos os dias, no horário escolhido" icon="clock">
    <div class="grid gap-5">
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:field variant="inline">
                <flux:switch wire:model.live="ativa" />
                <flux:label>Ativar coleta diária</flux:label>
            </flux:field>

            <flux:field>
                <flux:label>Horário (Bahia)</flux:label>
                <div class="flex items-center gap-2">
                    <flux:select wire:model.live="hora" :disabled="! $ativa" aria-label="Hora" class="max-w-24">
                        @foreach ($this::horas() as $opcao)
                            <flux:select.option wire:key="hora-{{ $opcao }}" :value="$opcao">{{ $opcao }}h</flux:select.option>
                        @endforeach
                    </flux:select>
                    <span class="text-zinc-500">:</span>
                    <flux:select wire:model.live="minuto" :disabled="! $ativa" aria-label="Minuto" class="max-w-24">
                        @foreach ($this::minutos() as $opcao)
                            <flux:select.option wire:key="minuto-{{ $opcao }}" :value="$opcao">{{ $opcao }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:error name="hora" />
                <flux:error name="minuto" />
            </flux:field>
        </div>

        <div class="flex flex-col gap-1 border-t border-zinc-200/80 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
            <flux:text size="sm">
                @if ($ultimaColeta)
                    Última coleta automática: @data($ultimaColeta)
                @else
                    Nenhuma coleta automática executada ainda.
                @endif
            </flux:text>

            <flux:text size="sm" class="text-zinc-500" wire:loading.remove wire:target="ativa,hora,minuto">Alterações salvas automaticamente.</flux:text>
            <flux:text size="sm" class="text-zinc-500" wire:loading wire:target="ativa,hora,minuto">Salvando...</flux:text>
        </div>
    </div>
</x-ui.panel>
