<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialAccount
 */
class FinancialAccountResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->enum($this->type),
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'agency' => $this->agency,
            'account_number' => $this->account_number,
            'opening_balance' => $this->money($this->opening_balance_cents),
            'opening_date' => $this->date($this->opening_date),
            'current_balance' => $this->money($this->current_balance_cents),
            'is_active' => $this->is_active,
        ];
    }
}
