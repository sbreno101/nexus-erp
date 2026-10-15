<?php

namespace App\Http\Requests\Crm;

use App\Enums\InteractionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInteractionRequest extends FormRequest
{
    /**
     * Registros que possuem histórico de interações.
     */
    public const array TYPES = ['customer', 'lead', 'opportunity'];

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
            'interactable_type' => ['required', Rule::in(self::TYPES)],
            'interactable_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(InteractionType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['occurred_at' => 'data da interação', 'type' => 'tipo de interação'];
    }
}
