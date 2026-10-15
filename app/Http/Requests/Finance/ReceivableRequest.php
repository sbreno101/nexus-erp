<?php

namespace App\Http\Requests\Finance;

use App\Models\Receivable;
use Illuminate\Validation\Rule;

class ReceivableRequest extends BillRequest
{
    protected function categoryType(): string
    {
        return 'income';
    }

    protected function partyRules(int $companyId): array
    {
        $current = $this->route('receivable');

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'contract_id' => [
                'nullable',
                'integer',
                // Se vinculado a um contrato, ele precisa ser do MESMO cliente.
                Rule::exists('contracts', 'id')->where('company_id', $companyId)->where('customer_id', (int) $this->input('customer_id')),
                // Espelha o índice UNIQUE (contract_id, competence): uma cobrança por contrato e competência.
                Rule::unique('receivables', 'contract_id')
                    ->where('competence', (string) $this->input('competence'))
                    ->ignore($current instanceof Receivable ? $current->id : null),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contract_id.unique' => 'Este contrato já possui uma cobrança nesta competência.',
        ];
    }
}
