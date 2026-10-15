<?php

namespace App\Http\Requests\Crm;

use App\Enums\LeadStatus;
use App\Http\Requests\Concerns\NormalizesMoney;
use App\Support\BrazilianDocument;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    use NormalizesMoney;

    public function authorize(): bool
    {
        return true;
    }

    protected function moneyFields(): array
    {
        return ['estimated_value' => 'estimated_value_cents'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyFields();

        if ($this->filled('document')) {
            $this->merge(['document' => BrazilianDocument::clean($this->input('document'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'document' => ['nullable', 'string', 'max:14'],
            'lead_source_id' => ['nullable', 'integer', Rule::exists('lead_sources', 'id')->where('company_id', $companyId)],
            // "Convertido" só é atingido pela conversão (Action), nunca pelo formulário.
            'status' => ['required', Rule::enum(LeadStatus::class)->except([LeadStatus::Converted])],
            'estimated_value_cents' => ['nullable', 'integer', 'min:0'],
            'owner_id' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', $companyId)->where('is_active', true)],
            'next_contact_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['next_contact_at' => 'data de retorno', 'company_name' => 'empresa'];
    }

    /**
     * @return array<string, mixed>
     */
    public function leadData(): array
    {
        return [...$this->safe()->except(['estimated_value']), 'estimated_value_cents' => (int) ($this->validated('estimated_value_cents') ?? 0)];
    }
}
