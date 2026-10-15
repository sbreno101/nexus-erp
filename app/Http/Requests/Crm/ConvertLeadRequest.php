<?php

namespace App\Http\Requests\Crm;

use App\Enums\PersonType;
use App\Rules\CpfCnpj;
use App\Support\BrazilianDocument;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dados do cliente que será criado a partir do lead. As mesmas regras de
 * documento e duplicidade do cadastro de clientes são aplicadas.
 */
class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['document' => BrazilianDocument::clean($this->input('document'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();
        $type = PersonType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(PersonType::class)],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'required',
                new CpfCnpj($type?->documentRule()),
                Rule::unique('customers', 'document')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'owner_id' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', $companyId)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['document.unique' => 'Já existe um cliente com este CPF/CNPJ. Vincule o lead ao cliente existente.'];
    }
}
