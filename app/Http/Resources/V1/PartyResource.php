<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\FormatsValues;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cliente ou fornecedor ("parte" de negócio).
 *
 * @mixin Customer
 */
class PartyResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->enum($this->type),
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'display_name' => $this->display_name,
            'document' => $this->document,
            'document_formatted' => $this->formatted_document,
            'state_registration' => $this->state_registration,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'status' => $this->enum($this->status),
            'notes' => $this->notes,
            'owner' => $this->when($this->resource instanceof Customer, fn () => $this->whenLoaded('owner', fn () => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null)),
            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map->only(['id', 'label', 'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state', 'is_primary'])),
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map->only(['id', 'name', 'position', 'email', 'phone', 'is_primary'])),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
