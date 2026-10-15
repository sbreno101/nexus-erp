<?php

namespace App\Http\Requests\Customers;

use App\Enums\RecordStatus;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

class SupplierRequest extends BusinessPartyRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function table(): string
    {
        return 'suppliers';
    }

    protected function currentId(): ?int
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier ? $supplier->id : null;
    }

    protected function extraRules(): array
    {
        return [
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }
}
