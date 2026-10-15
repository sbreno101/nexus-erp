<?php

namespace App\Http\Requests\Finance;

use Illuminate\Validation\Rule;

class PayableRequest extends BillRequest
{
    protected function categoryType(): string
    {
        return 'expense';
    }

    protected function partyRules(int $companyId): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
        ];
    }
}
