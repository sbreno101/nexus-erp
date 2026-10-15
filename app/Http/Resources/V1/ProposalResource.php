<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Proposal
 */
class ProposalResource extends JsonResource
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
            'opportunity_id' => $this->opportunity_id,
            'owner_id' => $this->owner_id,
            'issue_date' => $this->date($this->issue_date),
            'valid_until' => $this->date($this->valid_until),
            'subtotal' => $this->money($this->subtotal_cents),
            'discount' => $this->money($this->discount_cents),
            'total' => $this->money($this->total_cents),
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
            'sent_at' => $this->dateTime($this->sent_at),
            'approved_at' => $this->dateTime($this->approved_at),
            'rejected_at' => $this->dateTime($this->rejected_at),
            'rejection_reason' => $this->rejection_reason,
            'contract_id' => $this->whenLoaded('contract', fn () => $this->contract?->id),
            'items' => LineItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
