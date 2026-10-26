<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PeriodRequest extends FormRequest
{
    /** Maximum period window in days (inclusive), per the migration spec. */
    private const MAX_DAYS = 1100;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'periodo' => ['sometimes', 'nullable', Rule::in(['hoje', '7', '30'])],
            'inicio' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'fim' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Cross-field rules (mirrors the source model validator).
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return; // field errors take precedence, as in the source schema
            }

            $periodo = $this->input('periodo');
            $inicio = $this->input('inicio');
            $fim = $this->input('fim');

            if (blank($periodo) && (blank($inicio) || blank($fim))) {
                $validator->errors()->add(
                    '_periodo',
                    "informe 'periodo' (hoje/7/30) ou 'inicio' e 'fim'"
                );

                return;
            }

            if (filled($inicio) && filled($fim) && strcmp((string) $fim, (string) $inicio) < 0) {
                $validator->errors()->add('fim', 'fim anterior ao início');

                return;
            }

            if (filled($inicio) && filled($fim)) {
                $dias = (int) floor((strtotime((string) $fim) - strtotime((string) $inicio)) / 86400) + 1;

                if ($dias > self::MAX_DAYS) {
                    $validator->errors()->add('_periodo', 'período máximo de '.self::MAX_DAYS.' dias');
                }
            }
        });
    }
}
