<?php

namespace App\Http\Requests\Operations;

use App\Enums\ServiceOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mudança de status de uma OS. A obrigatoriedade do motivo (concluir e
 * cancelar) é regra de negócio e fica na Action ChangeServiceOrderStatus.
 */
class ChangeServiceOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ServiceOrderStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function targetStatus(): ServiceOrderStatus
    {
        return ServiceOrderStatus::from((string) $this->validated('status'));
    }
}
