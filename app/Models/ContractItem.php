<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Serviço/produto contratado (linha do contrato).
 */
#[Fillable([
    'catalog_item_id', 'description', 'quantity', 'unit', 'unit_price_cents',
    'discount_cents', 'total_cents', 'position',
])]
class ContractItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class)->withTrashed();
    }

    public function formattedQuantity(): string
    {
        $quantity = rtrim(rtrim((string) $this->quantity, '0'), '.');

        return str_replace('.', ',', $quantity);
    }
}
