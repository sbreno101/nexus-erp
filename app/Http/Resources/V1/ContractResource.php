<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'status' => $this->enum($this->status),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->display_name]),
            'proposal_id' => $this->proposal_id,
            'start_date' => $this->date($this->start_date),
            'end_date' => $this->date($this->end_date),
            'billing_frequency' => $this->enum($this->billing_frequency),
            'billing_day' => $this->billing_day,
            'amount' => $this->money($this->amount_cents),
            'next_billing_date' => $this->date($this->next_billing_date),
            'auto_renew' => $this->auto_renew,
            'renewal_months' => $this->renewal_months,
            'cancellation_reason' => $this->cancellation_reason,
            'items' => LineItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
