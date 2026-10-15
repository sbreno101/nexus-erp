<?php

namespace App\Http\Requests\Commercial;

use App\Enums\BillingFrequency;
use App\Http\Requests\Concerns\NormalizesLineItems;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de contratos (web e API). O valor periódico do contrato é a
 * soma dos itens, calculada no servidor.
 */
class ContractRequest extends FormRequest
{
    use NormalizesLineItems;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLineItems();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'company_unit_id' => ['nullable', 'integer', Rule::exists('company_units', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', $companyId)->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'billing_frequency' => ['required', Rule::enum(BillingFrequency::class)],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')->where('company_id', $companyId)->where('type', 'income')],
            'auto_renew' => ['boolean'],
            'renewal_months' => ['required', 'integer', 'between:1,120'],
            'signed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            ...$this->lineItemRules($companyId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'renewal_months' => 'meses de renovação',
            'signed_at' => 'data de assinatura',
            'auto_renew' => 'renovação automática',
        ];
    }
}
