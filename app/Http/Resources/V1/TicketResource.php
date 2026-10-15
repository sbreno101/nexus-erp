<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
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
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->enum($this->priority),
            'status' => $this->enum($this->status),
            'channel' => $this->enum($this->channel),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->display_name]),
            'contract_id' => $this->contract_id,
            'service_order_id' => $this->service_order_id,
            'ticket_category_id' => $this->ticket_category_id,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'first_response_at' => $this->dateTime($this->first_response_at),
            'resolved_at' => $this->dateTime($this->resolved_at),
            'closed_at' => $this->dateTime($this->closed_at),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message) => [
                'id' => $message->id,
                'body' => $message->body,
                'is_internal' => $message->is_internal,
                'user' => $message->user?->name,
                'created_at' => $this->dateTime($message->created_at),
            ])),
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
