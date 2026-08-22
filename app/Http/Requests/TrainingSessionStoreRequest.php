<?php

namespace App\Http\Requests;

use App\Models\SessionDuration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingSessionStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A sessão nasce com problema opcional e duração resolvida pelo lookup:
     * fora de 30, 45 e 60 não existe duração, e o pedido é recusado (US-2.1).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'problem_id' => ['sometimes', 'nullable', 'integer', Rule::exists('problems', 'id')],
            'duration_minutes' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::in(SessionDuration::query()->pluck('minutes')->all()),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'duration_minutes.in' => 'A duração do treino é de 30, 45 ou 60 minutos.',
            'problem_id.exists' => 'Este problema não existe no catálogo.',
        ];
    }
}
