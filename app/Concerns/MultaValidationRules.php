<?php

namespace App\Concerns;

use App\Enums\MultaStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait MultaValidationRules
{
    /**
     * Get the rules used to validate a manual fine.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function multaRules(): array
    {
        return [
            'termoId' => ['required', 'integer', Rule::exists('termos', 'id')->where('tipo', 'municipio')],
            'gestor' => ['required', 'string', 'max:120'],
            'processoNumero' => ['required', 'string', 'max:30'],
            'dataMulta' => ['required', 'date_format:Y-m-d'],
            'dataVencimento' => ['required', 'date_format:Y-m-d', 'after_or_equal:dataMulta'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'situacao' => ['required', Rule::enum(MultaStatus::class)],
            'comprovante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * Custom validation messages (PT-BR).
     *
     * @return array<string, string>
     */
    protected function multaMessages(): array
    {
        return [
            'termoId.required' => 'Selecione o município.',
            'termoId.integer' => 'O município selecionado não é válido.',
            'termoId.exists' => 'O município selecionado não é válido.',
            'gestor.required' => 'Informe o gestor.',
            'gestor.string' => 'O gestor deve ser um texto.',
            'gestor.max' => 'O gestor deve ter no máximo 120 caracteres.',
            'processoNumero.required' => 'Informe o número do processo.',
            'processoNumero.string' => 'O número do processo deve ser um texto.',
            'processoNumero.max' => 'O número do processo deve ter no máximo 30 caracteres.',
            'dataMulta.required' => 'Informe a data da multa.',
            'dataMulta.date_format' => 'A data da multa é inválida.',
            'dataVencimento.required' => 'Informe a data do vencimento.',
            'dataVencimento.date_format' => 'A data do vencimento é inválida.',
            'dataVencimento.after_or_equal' => 'A data do vencimento deve ser igual ou posterior à data da multa.',
            'valor.required' => 'Informe o valor da multa.',
            'valor.numeric' => 'O valor da multa deve ser um número.',
            'valor.min' => 'O valor da multa deve ser maior que zero.',
            'situacao.required' => 'Informe a situação da multa.',
            'situacao.enum' => 'A situação informada não é válida.',
            'comprovante.file' => 'O anexo deve ser um arquivo.',
            'comprovante.mimes' => 'O anexo deve ser um arquivo PDF, JPG ou PNG.',
            'comprovante.max' => 'O anexo deve ter no máximo 10 MB.',
        ];
    }

    /**
     * Friendly attribute names used by the validation messages.
     *
     * @return array<string, string>
     */
    protected function multaAttributes(): array
    {
        return [
            'termoId' => 'município',
            'gestor' => 'gestor',
            'processoNumero' => 'número do processo',
            'dataMulta' => 'data da multa',
            'dataVencimento' => 'data do vencimento',
            'valor' => 'valor',
            'situacao' => 'situação',
            'comprovante' => 'comprovante',
        ];
    }
}
