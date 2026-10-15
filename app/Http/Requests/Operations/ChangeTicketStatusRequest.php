<?php

namespace App\Http\Requests\Operations;

use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mudança de status de um chamado. Se a transição é permitida, quem decide
 * é a Action ChangeTicketStatus (máquina de estados do Enum).
 */
class ChangeTicketStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function targetStatus(): TicketStatus
    {
        return TicketStatus::from((string) $this->validated('status'));
    }
}
