<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ];
    }

    /**
     * Messages for the username pattern (source description: letters, digits,
     * dot, hyphen and underscore).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'username: letras, dígitos, ponto, hífen e sublinhado',
        ];
    }
}
