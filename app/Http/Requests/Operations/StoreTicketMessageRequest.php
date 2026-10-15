<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Resposta ao cliente ou comentário interno (is_internal = true) num chamado.
 */
class StoreTicketMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'is_internal' => 'comentário interno',
        ];
    }
}
