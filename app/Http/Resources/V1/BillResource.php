<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\Receivable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conta a receber ou a pagar.
 *
 * @mixin Receivable
 */
class BillResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isReceivable = $this->resource instanceof Receivable;
        $party = $isReceivable ? 'customer' : 'supplier';

        return [
            'id' => $this->id,
            'kind' => $isReceivable ? 'receivable' : 'payable',
            'description' => $this->description,
            'document_number' => $this->document_number,
            $party => $this->whenLoaded($party, function () use ($party): ?array {
                $related = $this->resource->getRelation($party);

                return $related ? ['id' => $related->id, 'name' => $related->display_name] : null;
            }),
            'contract_id' => $this->when($isReceivable, fn () => $this->contract_id),
            'financial_category_id' => $this->financial_category_id,
            'cost_center_id' => $this->cost_center_id,
            'amount' => $this->money($this->amount_cents),
            'paid' => $this->money($this->paid_cents),
            'outstanding' => $this->money($this->outstandingCents()),
            'issue_date' => $this->date($this->issue_date),
            'due_date' => $this->date($this->due_date),
            'competence' => $this->competence?->format('Y-m'),
            'status' => $this->enum($this->status),
            'is_overdue' => $this->isOverdue(),
            'paid_at' => $this->date($this->paid_at),
            'cancelled_at' => $this->dateTime($this->cancelled_at),
            'cancellation_reason' => $this->cancellation_reason,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
