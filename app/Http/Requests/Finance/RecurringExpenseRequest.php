<?php

namespace App\Http\Requests\Finance;

use App\Enums\BillingFrequency;
use App\Http\Requests\Concerns\NormalizesMoney;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringExpenseRequest extends FormRequest
{
    use NormalizesMoney;

    public function authorize(): bool
    {
        return true;
    }

    protected function moneyFields(): array
    {
        return ['amount' => 'amount_cents'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')->where('company_id', $companyId)->where('type', 'expense')],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'frequency' => ['required', Rule::enum(BillingFrequency::class)],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['due_day' => 'dia de vencimento', 'frequency' => 'periodicidade'];
    }
}
