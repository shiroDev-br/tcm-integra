<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TermRequest extends FormRequest
{
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
     * Constraints run on the raw string (as in the source schema); the term is
     * whitespace-collapsed at save time by the service.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'termo' => ['required', 'string', 'min:2', 'max:120'],
            'tipo' => ['sometimes', 'in:municipio,empresa'],
            'ativo' => ['sometimes', 'boolean'],
            'variacoes' => ['sometimes', 'array', 'max:10'],
            'variacoes.*' => ['string'],
        ];
    }

    /**
     * Whitespace-only terms collapse to an empty string at save time.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $termo = $this->input('termo');

            if (! is_string($termo) || mb_strlen($termo) < 2) {
                return;
            }

            if (trim((string) preg_replace('/\s+/u', ' ', $termo)) === '') {
                $validator->errors()->add('termo', 'termo vazio');
            }
        });
    }
}
