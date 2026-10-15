<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\Concerns\NormalizesMoney;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Uma oportunidade negocia com um CLIENTE ou com um LEAD (pelo menos um).
 */
class OpportunityRequest extends FormRequest
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'title' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'required_without:lead_id', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'lead_id' => ['nullable', 'required_without:customer_id', 'integer', Rule::exists('leads', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'pipeline_stage_id' => ['required', 'integer', Rule::exists('pipeline_stages', 'id')->where('company_id', $companyId)],
            'owner_id' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', $companyId)->where('is_active', true)],
            'estimated_value_cents' => ['required', 'integer', 'min:0'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required_without' => 'Informe o cliente ou o lead da oportunidade.',
            'lead_id.required_without' => 'Informe o cliente ou o lead da oportunidade.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['expected_close_date' => 'previsão de fechamento', 'probability' => 'probabilidade', 'lead_id' => 'lead'];
    }
}
