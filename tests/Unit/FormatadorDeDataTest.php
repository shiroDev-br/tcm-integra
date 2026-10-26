<?php

use App\Services\FormatadorDeData;
use Carbon\CarbonImmutable;

test('data do banco e exibida como dd/mm/aaaa', function () {
    expect(FormatadorDeData::data('2026-10-01'))->toBe('01/10/2026');
    expect(FormatadorDeData::data(CarbonImmutable::parse('2026-09-01')))->toBe('01/09/2026');
});

test('data que ja chega no formato brasileiro e mantida', function () {
    expect(FormatadorDeData::data('05/01/2026'))->toBe('05/01/2026');
    expect(FormatadorDeData::dataHora('23/10/2026'))->toBe('23/10/2026');
});

test('data e hora em utc sao exibidas no fuso do tcm', function () {
    config(['tcm.timezone' => 'America/Bahia']);

    expect(FormatadorDeData::dataHora('2026-10-24T03:03:56+00:00'))->toBe('24/10/2026 00:03');
    expect(FormatadorDeData::dataHora('2026-10-24T00:03:56-03:00'))->toBe('24/10/2026 00:03');
});

test('valores vazios ou invalidos nao quebram a exibicao', function () {
    expect(FormatadorDeData::data(null))->toBe('—');
    expect(FormatadorDeData::dataHora(''))->toBe('—');
    expect(FormatadorDeData::data('sem data'))->toBe('sem data');
});

test('busca em dd/mm/aaaa e convertida para o formato do banco', function () {
    expect(FormatadorDeData::paraIso('15/10/2026'))->toBe('2026-10-15');
    expect(FormatadorDeData::paraIso('2026-10'))->toBe('2026-10');
});
