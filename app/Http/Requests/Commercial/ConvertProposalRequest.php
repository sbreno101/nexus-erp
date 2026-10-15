<?php

namespace App\Http\Requests\Commercial;

use App\Enums\BillingFrequency;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dados próprios do contrato informados na conversão de uma proposta.
 */
class ConvertProposalRequest extends FormRequest
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
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'billing_frequency' => ['required', Rule::enum(BillingFrequency::class)],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'auto_renew' => ['boolean'],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')
                ->where('company_id', app(CurrentCompany::class)->id())->where('type', 'income')],
            'activate' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['auto_renew' => 'renovação automática', 'activate' => 'ativar contrato'];
    }
}
