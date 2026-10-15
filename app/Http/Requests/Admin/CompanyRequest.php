<?php

namespace App\Http\Requests\Admin;

use App\Enums\CompanyStatus;
use App\Http\Requests\Customers\BusinessPartyRequest;
use App\Models\Company;
use App\Rules\CpfCnpj;
use App\Support\BrazilianDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro/edição de empresas (tenants) pelo superadministrador.
 */
class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => BrazilianDocument::clean($this->input('cnpj')),
            'zip_code' => $this->filled('zip_code') ? preg_replace('/\D/', '', (string) $this->input('zip_code')) : null,
            'state' => $this->filled('state') ? strtoupper((string) $this->input('state')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = $this->route('company');

        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'required',
                new CpfCnpj('cnpj'),
                // Índice UNIQUE global: vale também para empresas excluídas.
                Rule::unique('companies', 'cnpj')->ignore($company instanceof Company ? $company->id : null),
            ],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'zip_code' => ['nullable', 'digits:8'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(BusinessPartyRequest::STATES)],
            'status' => ['required', Rule::enum(CompanyStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnpj.unique' => 'Já existe uma empresa cadastrada com este CNPJ.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'razão social',
            'trade_name' => 'nome fantasia',
            'cnpj' => 'CNPJ',
        ];
    }
}
