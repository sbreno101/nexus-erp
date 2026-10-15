<?php

namespace App\Http\Requests\Finance;

use App\Enums\FinancialAccountType;
use App\Http\Requests\Concerns\NormalizesMoney;
use App\Models\FinancialAccount;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro de contas financeiras. O saldo inicial só é aceito na criação.
 */
class FinancialAccountRequest extends FormRequest
{
    use NormalizesMoney;

    public function authorize(): bool
    {
        return true;
    }

    protected function moneyFields(): array
    {
        return ['opening_balance' => 'opening_balance_cents'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->route('account');
        $isCreating = ! $account instanceof FinancialAccount;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('financial_accounts', 'name')
                ->where('company_id', app(CurrentCompany::class)->id())->ignore($isCreating ? null : $account->id)],
            'type' => ['required', Rule::enum(FinancialAccountType::class)],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_code' => ['nullable', 'string', 'max:5'],
            'agency' => ['nullable', 'string', 'max:20'],
            'account_number' => ['nullable', 'string', 'max:30'],
            'opening_balance_cents' => $isCreating ? ['required', 'integer'] : ['prohibited'],
            'opening_date' => $isCreating ? ['required', 'date', 'before_or_equal:today'] : ['prohibited'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_balance_cents.prohibited' => 'O saldo inicial não pode ser alterado. Use um lançamento avulso para corrigir o saldo.',
            'opening_date.prohibited' => 'A data de abertura não pode ser alterada.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['opening_balance_cents' => 'saldo inicial', 'opening_date' => 'data de abertura', 'bank_code' => 'código do banco'];
    }
}
