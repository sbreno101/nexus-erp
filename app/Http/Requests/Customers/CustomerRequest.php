<?php

namespace App\Http\Requests\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\Rule;

/**
 * Validação do cadastro de clientes (usada pela tela web e pela API).
 * A autorização (Policy) é feita no Controller, antes de chegar aqui.
 */
class CustomerRequest extends BusinessPartyRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function table(): string
    {
        return 'customers';
    }

    protected function currentId(): ?int
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer ? $customer->id : null;
    }

    protected function extraRules(): array
    {
        return [
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'owner_id' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')
                ->where('company_id', app(CurrentCompany::class)->id())],
        ];
    }
}
