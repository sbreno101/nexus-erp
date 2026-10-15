<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Requisição genérica de mudança de status (propostas e contratos).
 * O Enum alvo é validado pelo controller (cada recurso tem o seu).
 */
class ChangeStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'max:30'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
