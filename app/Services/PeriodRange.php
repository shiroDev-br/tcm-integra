<?php

namespace App\Services;

use App\Enums\PeriodPreset;
use App\Exceptions\InvalidPeriod;

/**
 * Search period range (start/end in Y-m-d) — resolved from a named preset
 * (hoje/7/30) or an explicit interval.
 */
final class PeriodRange
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
    ) {}

    /**
     * Resolves the API input: `periodo` preset or `inicio`/`fim` interval.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $preset = $input['periodo'] ?? null;

        if (filled($preset)) {
            return self::fromPreset(PeriodPreset::from((string) $preset));
        }

        return self::fromInterval((string) ($input['inicio'] ?? ''), (string) ($input['fim'] ?? ''));
    }

    public static function fromPreset(PeriodPreset $preset, ?string $base = null): self
    {
        $base ??= Clock::today();
        $dias = $preset->days() - 1;
        $inicio = (new \DateTimeImmutable($base))->modify("-{$dias} days")->format('Y-m-d');

        return new self($inicio, $base);
    }

    public static function fromInterval(string $start, string $end): self
    {
        $inicio = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $fim = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);

        if ($inicio === false || $fim === false || $inicio->format('Y-m-d') !== $start || $fim->format('Y-m-d') !== $end) {
            throw new InvalidPeriod('período inválido, use AAAA-MM-DD');
        }

        if ($fim < $inicio) {
            throw new InvalidPeriod('fim anterior ao início');
        }

        return new self($start, $end);
    }

    public function contains(string $data): bool
    {
        return $this->start <= $data && $data <= $this->end;
    }

    /**
     * Period payload in the API contract shape ({"inicio": ..., "fim": ...}).
     *
     * @return array{inicio: string, fim: string}
     */
    public function toArray(): array
    {
        return ['inicio' => $this->start, 'fim' => $this->end];
    }

    /**
     * Number of days of the interval (inclusive).
     */
    public function days(): int
    {
        $inicio = new \DateTimeImmutable($this->start);
        $fim = new \DateTimeImmutable($this->end);

        return (int) $inicio->diff($fim)->days;
    }
}
