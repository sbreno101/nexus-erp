<?php

namespace App\Http\Requests\Crm;

use App\Enums\Priority;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
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
            'taskable_type' => ['nullable', Rule::in(self::TYPES), 'required_with:taskable_id'],
            'taskable_id' => ['nullable', 'integer', 'required_with:taskable_type'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'due_at' => ['nullable', 'date'],
            // O responsável precisa ter vínculo ATIVO com a empresa ativa.
            'assigned_to' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')
                ->where('company_id', app(CurrentCompany::class)->id())
                ->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['due_at' => 'data de retorno'];
    }
}
