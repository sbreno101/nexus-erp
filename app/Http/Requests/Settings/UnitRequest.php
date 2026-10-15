<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Customers\BusinessPartyRequest;
use App\Models\CompanyUnit;
use App\Rules\CpfCnpj;
use App\Support\BrazilianDocument;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do cadastro de unidades (filiais/escritórios) da empresa ativa.
 */
class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalização: CNPJ e CEP sem máscara, código e UF em maiúsculas.
     * CNPJ vazio vira null (o campo é opcional).
     */
    protected function prepareForValidation(): void
    {
        $cnpj = BrazilianDocument::clean($this->input('cnpj'));

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'cnpj' => $cnpj !== '' ? $cnpj : null,
            'zip_code' => $this->filled('zip_code') ? preg_replace('/\D/', '', (string) $this->input('zip_code')) : null,
            'state' => $this->filled('state') ? strtoupper((string) $this->input('state')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('company_units', 'code')
                    ->where('company_id', app(CurrentCompany::class)->id())
                    ->whereNull('deleted_at')
                    ->ignore($unit instanceof CompanyUnit ? $unit->id : null),
            ],
            'cnpj' => ['nullable', new CpfCnpj('cnpj')],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'zip_code' => ['nullable', 'digits:8'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(BusinessPartyRequest::STATES)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Já existe uma unidade com este código nesta empresa.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'code' => 'código',
            'cnpj' => 'CNPJ',
            'is_active' => 'situação',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function unitData(): array
    {
        return [
            ...$this->validated(),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
