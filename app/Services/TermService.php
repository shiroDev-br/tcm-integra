<?php

namespace App\Services;

use App\Exceptions\TermAlreadyExists;
use App\Exceptions\TermNotFound;
use App\Models\Term;
use Illuminate\Database\QueryException;

/**
 * Search term CRUD (municípios/empresas) with domain rules.
 */
class TermService
{
    /**
     * Terms ordered by id — only the TermoOut contract fields.
     *
     * @return list<array{id: int, termo: string, tipo: string, ativo: bool, variacoes: list<string>}>
     */
    public function list(bool $onlyActive = false): array
    {
        $query = Term::query()->orderBy('id');

        if ($onlyActive) {
            $query->ativo();
        }

        return array_values($query->get()->map(fn (Term $t): array => $this->output($t))->all());
    }

    /**
     * @param  array<string, mixed>  $data  validated TermRequest data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $termo = $this->cleanTerm($data['termo']);

        if ($this->exists($termo)) {
            throw new TermAlreadyExists("termo '{$termo}' já cadastrado");
        }

        try {
            $registro = Term::query()->create([
                'termo' => $termo,
                'tipo' => $data['tipo'] ?? 'municipio',
                'ativo' => $data['ativo'] ?? true,
                'variacoes' => $this->cleanVariations($data['variacoes'] ?? []),
                'created_at' => Clock::nowUtcIso(),
            ]);
        } catch (QueryException) {
            throw new TermAlreadyExists("termo '{$termo}' já cadastrado");
        }

        return $this->output($registro);
    }

    /**
     * Full replacement, matching the source schema defaults.
     *
     * @param  array<string, mixed>  $data  validated TermRequest data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $termo = $this->cleanTerm($data['termo']);

        if (! $this->find($id)) {
            throw new TermNotFound("termo {$id} não encontrado");
        }

        $duplicado = Term::query()
            ->where('termo', $termo)
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicado) {
            throw new TermAlreadyExists("termo '{$termo}' já cadastrado");
        }

        try {
            Term::query()->where('id', $id)->update([
                'termo' => $termo,
                'tipo' => $data['tipo'] ?? 'municipio',
                'ativo' => $data['ativo'] ?? true,
                'variacoes' => $this->cleanVariations($data['variacoes'] ?? []),
            ]);
        } catch (QueryException) {
            throw new TermAlreadyExists("termo '{$termo}' já cadastrado");
        }

        return $this->output($this->find($id));
    }

    public function delete(int $id): void
    {
        $term = $this->find($id);

        if ($term === null) {
            throw new TermNotFound("termo {$id} não encontrado");
        }

        $term->delete();
    }

    private function exists(string $termo): bool
    {
        return Term::query()->where('termo', $termo)->exists();
    }

    private function find(int $id): ?Term
    {
        return Term::query()->find($id);
    }

    /**
     * Whitespace-collapsed term text (source `termo_limpo` validator).
     */
    private function cleanTerm(mixed $termo): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $termo));
    }

    /**
     * Collapses whitespace, drops empty items and caps at 10 (source validator).
     *
     * @return list<string>
     */
    private function cleanVariations(mixed $variacoes): array
    {
        $limpas = [];

        foreach ((array) $variacoes as $item) {
            $valor = trim((string) preg_replace('/\s+/u', ' ', (string) $item));

            if ($valor !== '') {
                $limpas[] = $valor;
            }
        }

        return array_slice($limpas, 0, 10);
    }

    /**
     * @return array{id: int, termo: string, tipo: string, ativo: bool, variacoes: list<string>}
     */
    private function output(Term $t): array
    {
        return [
            'id' => (int) $t->id,
            'termo' => (string) $t->termo,
            'tipo' => $t->tipo->value,
            'ativo' => (bool) $t->ativo,
            'variacoes' => array_values((array) $t->variacoes),
        ];
    }
}
