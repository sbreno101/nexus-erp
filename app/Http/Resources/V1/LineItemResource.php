<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\ProposalItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item de proposta ou contrato.
 *
 * @mixin ProposalItem
 */
class LineItemResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalog_item_id' => $this->catalog_item_id,
            'description' => $this->description,
            'quantity' => (string) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->money($this->unit_price_cents),
            'discount' => $this->money($this->discount_cents),
            'total' => $this->money($this->total_cents),
        ];
    }
}
