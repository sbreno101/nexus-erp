<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receivable_id' => $this->receivable_id,
            'payable_id' => $this->payable_id,
            'financial_account_id' => $this->financial_account_id,
            'amount' => $this->money($this->amount_cents),
            'paid_on' => $this->date($this->paid_on),
            'method' => $this->enum($this->method),
            'status' => $this->enum($this->status),
            'notes' => $this->notes,
            'idempotency_key' => $this->idempotency_key,
            'reversed_at' => $this->dateTime($this->reversed_at),
            'reversal_reason' => $this->reversal_reason,
        ];
    }
}
