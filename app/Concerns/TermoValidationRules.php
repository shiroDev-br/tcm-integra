<?php

namespace App\Concerns;

use App\Enums\TermType;
use Illuminate\Contracts\Validation\ValidationRule;

trait TermoValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function termoRules(): array
    {
        return [
            'termo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:'.implode(',', $this->tiposTermosDisponiveis())],
            'ativo' => ['required', 'boolean'],
            'variacoes' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function termoMessages(): array
    {
        return [
            'termo.required' => 'O campo termo é obrigatório.',
            'termo.string' => 'O campo termo deve ser uma string.',
            'termo.max' => 'O campo termo não pode ter mais de 255 caracteres.',
            'tipo.required' => 'O campo tipo é obrigatório.',
            'tipo.in' => 'O tipo deve ser um dos valores permitidos.',
            'ativo.required' => 'O campo ativo é obrigatório.',
            'ativo.boolean' => 'O campo ativo deve ser um valor booleano.',
            'variacoes.array' => 'As variações devem ser um array.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function termoAttributes(): array
    {
        return [
            'termo' => 'Termo',
            'tipo' => 'Tipo',
            'ativo' => 'Ativo',
            'variacoes' => 'Variações',
        ];
    }

    /**
     * @return list<string>
     */
    protected function tiposTermosDisponiveis(): array
    {
        $tipos = [];

        foreach (TermType::cases() as $case) {
            $tipos[] = $case->value;
        }

        return $tipos;
    }
}
