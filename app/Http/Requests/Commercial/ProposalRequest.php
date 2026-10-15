<?php

namespace App\Http\Requests\Commercial;

use App\Http\Requests\Concerns\NormalizesLineItems;
use App\Http\Requests\Concerns\NormalizesMoney;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de propostas (web e API). Totais NÃO são aceitos do cliente:
 * são calculados no servidor pelo LineItemCalculator.
 */
class ProposalRequest extends FormRequest
{
    use NormalizesLineItems, NormalizesMoney;

    public function authorize(): bool
    {
        return true;
    }

    protected function moneyFields(): array
    {
        return ['discount' => 'discount_cents'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyFields();
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
            'opportunity_id' => ['nullable', 'integer', Rule::exists('opportunities', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', $companyId)->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'payment_terms' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'discount_cents' => ['nullable', 'integer', 'min:0'],
            ...$this->lineItemRules($companyId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['opportunity_id' => 'oportunidade', 'payment_terms' => 'condições comerciais', 'discount_cents' => 'desconto geral'];
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalData(): array
    {
        return [...$this->validated(), 'discount_cents' => (int) ($this->validated('discount_cents') ?? 0)];
    }
}
