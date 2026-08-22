<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrainingSessionUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * O dono da sessão nunca vem do cliente: `user_id` fica de fora das regras,
     * e é o que sai de `validated()` que chega ao model (US-1.4).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'elapsed_seconds' => ['sometimes', 'integer', 'min:0'],
            'nodes' => ['sometimes', 'array'],
            'edges' => ['sometimes', 'array'],
            'checks' => ['sometimes', 'array'],
            'estimate' => ['sometimes', 'array'],
        ];
    }
}
