<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\NormalizesMoney;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRequest extends FormRequest
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
        $account = Rule::exists('financial_accounts', 'id')
            ->where('company_id', app(CurrentCompany::class)->id())
            ->where('is_active', true)
            ->whereNull('deleted_at');

        return [
            'from_account_id' => ['required', 'integer', $account],
            'to_account_id' => ['required', 'integer', 'different:from_account_id', $account],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'transferred_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['transferred_on' => 'data da transferência'];
    }
}
