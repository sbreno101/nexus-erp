<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrder
 */
class ServiceOrderResource extends JsonResource
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
            'description' => $this->description,
            'priority' => $this->enum($this->priority),
            'status' => $this->enum($this->status),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->display_name]),
            'contract_id' => $this->contract_id,
            'catalog_item_id' => $this->catalog_item_id,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'due_date' => $this->date($this->due_date),
            'is_overdue' => $this->isOverdue(),
            'started_at' => $this->dateTime($this->started_at),
            'completed_at' => $this->dateTime($this->completed_at),
            'resolution' => $this->resolution,
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
