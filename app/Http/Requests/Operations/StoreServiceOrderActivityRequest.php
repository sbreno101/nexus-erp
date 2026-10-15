<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro de uma atividade executada na OS (descrição + tempo gasto).
 * O tempo é limitado a 1440 minutos (24 horas) por registro.
 */
class StoreServiceOrderActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:2000'],
            'minutes_spent' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'minutes_spent' => 'tempo gasto (minutos)',
        ];
    }
}
