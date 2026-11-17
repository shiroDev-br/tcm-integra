<?php

use App\Enums\SearchStatus;
use App\Livewire\Dashboard\Index;
use App\Models\SearchRun;
use App\Models\User;
use App\Services\Clock;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard avisa quais cadastros a busca de hoje encontrou', function () {
    SearchRun::query()->create([
        'periodo_inicio' => Clock::today(),
        'periodo_fim' => Clock::today(),
        'termos' => 3,
        'status' => SearchStatus::Ok,
        'termos_encontrados' => [['termo_id' => 5, 'termo' => 'Potiraguá', 'ocorrencias' => 2, 'processos' => 1]],
        'created_at' => now(),
    ]);

    Livewire::withoutLazyLoading()->test(Index::class)
        ->assertSee('Cadastros encontrados na busca de hoje')
        ->assertSee('Potiraguá (2)');
});

test('dashboard sem busca hoje nao exibe o aviso', function () {
    SearchRun::query()->create([
        'periodo_inicio' => '2026-01-01',
        'periodo_fim' => '2026-01-07',
        'termos' => 3,
        'status' => SearchStatus::Ok,
        'termos_encontrados' => [['termo_id' => 5, 'termo' => 'Potiraguá', 'ocorrencias' => 2, 'processos' => 1]],
        'created_at' => now()->subMonths(10),
    ]);

    Livewire::withoutLazyLoading()->test(Index::class)
        ->assertDontSee('Cadastros encontrados na busca de hoje');
});
