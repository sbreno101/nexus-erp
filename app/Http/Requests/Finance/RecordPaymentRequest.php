<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\NormalizesMoney;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Baixa de um título. A "idempotency_key" (UUID) vem do formulário/cliente
 * da API e garante que o mesmo envio não seja processado duas vezes.
 */
class RecordPaymentRequest extends FormRequest
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
            'financial_account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')
                ->where('company_id', app(CurrentCompany::class)->id())->where('is_active', true)->whereNull('deleted_at')],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['paid_on.before_or_equal' => 'A data do pagamento não pode estar no futuro.'];
    }
}
