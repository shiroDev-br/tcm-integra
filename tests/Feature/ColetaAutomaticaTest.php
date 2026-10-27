<?php

use App\Livewire\Buscas\ColetaAutomatica;
use App\Models\Configuracao;
use App\Models\User;
use App\Services\Clock;
use App\Services\SearchService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;
use Livewire\Livewire;

function esperarColetaDeHoje(int $vezes = 1): void
{
    test()->mock(SearchService::class)
        ->shouldReceive('execute')
        ->with(['periodo' => 'hoje'])
        ->times($vezes)
        ->andReturn([]);
}

test('ativar o switch salva na hora com o horario sugerido', function () {
    Livewire::test(ColetaAutomatica::class)
        ->set('ativa', true)
        ->assertSet('hora', '07')
        ->assertSet('minuto', '00')
        ->assertHasNoErrors();

    $configuracao = Configuracao::atual();

    expect($configuracao->coleta_automatica_ativa)->toBeTrue()
        ->and($configuracao->horario_coleta)->toBe('07:00');
});

test('alterar o horario salva na hora', function () {
    Livewire::test(ColetaAutomatica::class)
        ->set('ativa', true)
        ->set('hora', '07')
        ->set('minuto', '30')
        ->assertHasNoErrors();

    $configuracao = Configuracao::atual();

    expect($configuracao->coleta_automatica_ativa)->toBeTrue()
        ->and($configuracao->horario_coleta)->toBe('07:30');
});

test('escolher 14h salva o horario', function () {
    Configuracao::agendarColeta(true, '07:30');

    Livewire::test(ColetaAutomatica::class)
        ->set('hora', '14')
        ->assertHasNoErrors();

    expect(Configuracao::atual()->horario_coleta)->toBe('14:30');
});

test('painel recusa horario invalido e mantem o anterior', function (string $campo, string $valor) {
    Configuracao::agendarColeta(true, '07:30');

    Livewire::test(ColetaAutomatica::class)
        ->set($campo, $valor)
        ->assertHasErrors([$campo]);

    expect(Configuracao::atual()->horario_coleta)->toBe('07:30');
})->with([
    'hora vazia' => ['hora', ''],
    'hora 24' => ['hora', '24'],
    'minuto fora do passo' => ['minuto', '07'],
]);

test('painel permite desativar sem horario', function () {
    Configuracao::agendarColeta(true, '07:30');

    Livewire::test(ColetaAutomatica::class)
        ->assertSet('hora', '07')
        ->assertSet('minuto', '30')
        ->set('ativa', false)
        ->assertHasNoErrors();

    expect(Configuracao::atual()->coleta_automatica_ativa)->toBeFalse();
});

test('coleta fica pendente apenas apos o horario e uma vez por dia', function () {
    $configuracao = Configuracao::agendarColeta(true, '07:30');

    expect($configuracao->coletaPendente('2026-10-27', '07:29'))->toBeFalse()
        ->and($configuracao->coletaPendente('2026-10-27', '07:30'))->toBeTrue()
        ->and($configuracao->coletaPendente('2026-10-27', '15:00'))->toBeTrue();

    $configuracao->reservarColetaDoDia('2026-10-27');

    expect($configuracao->coletaPendente('2026-10-27', '15:00'))->toBeFalse()
        ->and($configuracao->coletaPendente('2026-10-28', '07:30'))->toBeTrue();
});

test('coleta desativada nunca fica pendente', function () {
    $configuracao = Configuracao::agendarColeta(false, '07:30');

    expect($configuracao->coletaPendente('2026-10-27', '23:59'))->toBeFalse();
});

test('comando coleta somente o dia atual uma vez por dia', function () {
    Configuracao::agendarColeta(true, '00:00');
    esperarColetaDeHoje();

    $this->artisan('tcm:coleta-automatica')->assertSuccessful();
    $this->artisan('tcm:coleta-automatica')->assertSuccessful();

    expect(Configuracao::atual()->ultima_coleta_automatica)->toBe(Clock::today());
});

test('comando nao coleta quando desativado', function () {
    Configuracao::agendarColeta(false, '00:00');
    esperarColetaDeHoje(0);

    $this->artisan('tcm:coleta-automatica')->assertSuccessful();
});

function eventoDaColetaAutomatica(): Event
{
    return collect(Schedule::events())
        ->first(fn (Event $evento): bool => str_contains((string) $evento->command, 'tcm:coleta-automatica'));
}

test('comando esta agendado a cada minuto', function () {
    expect(eventoDaColetaAutomatica()->expression)->toBe('* * * * *');
});

test('agendador so inicia o comando quando a coleta esta pendente', function () {
    Configuracao::agendarColeta(false, '00:00');
    expect(eventoDaColetaAutomatica()->filtersPass(app()))->toBeFalse();

    Configuracao::agendarColeta(true, '00:00');
    expect(eventoDaColetaAutomatica()->filtersPass(app()))->toBeTrue();

    Configuracao::atual()->reservarColetaDoDia(Clock::today());
    expect(eventoDaColetaAutomatica()->filtersPass(app()))->toBeFalse();
});

test('tela de buscas exibe o painel de coleta automatica', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('buscas.index'))
        ->assertOk()
        ->assertSee('Coleta automática');
});
