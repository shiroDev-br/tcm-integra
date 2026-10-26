<?php

use App\Exceptions\InvalidPeriod;
use App\Services\Clock;
use App\Services\PeriodRange;

test('presets nomeados', function () {
    $hoje = PeriodRange::fromInput(['periodo' => 'hoje']);
    expect($hoje->start)->toBe(Clock::today());
    expect($hoje->end)->toBe($hoje->start);

    $sete = PeriodRange::fromInput(['periodo' => '7']);
    expect($sete->days())->toBe(6);
});

test('intervalo explicito', function () {
    $periodo = PeriodRange::fromInput(['inicio' => '2026-09-01', 'fim' => '2026-09-30']);

    expect($periodo->start)->toBe('2026-09-01');
    expect($periodo->end)->toBe('2026-09-30');
    expect($periodo->contains('2026-09-15'))->toBeTrue();
    expect($periodo->contains('2026-10-01'))->toBeFalse();
    expect($periodo->toArray())->toBe(['inicio' => '2026-09-01', 'fim' => '2026-09-30']);
});

test('periodo invalido', function () {
    expect(fn (): PeriodRange => PeriodRange::fromInterval('2026-13-45', '2026-10-01'))
        ->toThrow(InvalidPeriod::class, 'período inválido, use AAAA-MM-DD');

    expect(fn (): PeriodRange => PeriodRange::fromInterval('2026-09-30', '2026-09-01'))
        ->toThrow(InvalidPeriod::class, 'fim anterior ao início');
});
