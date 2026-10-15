<?php

namespace App\Http\Requests\Finance;

use App\Enums\TransactionDirection;
use App\Http\Requests\Concerns\NormalizesMoney;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualTransactionRequest extends FormRequest
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
        return [
            'direction' => ['required', Rule::enum(TransactionDirection::class)],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')->where('company_id', app(CurrentCompany::class)->id())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['direction' => 'tipo de lançamento', 'occurred_on' => 'data'];
    }
}
