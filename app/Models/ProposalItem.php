<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item (linha) de uma proposta. Não possui company_id próprio: herda o
 * isolamento da proposta à qual pertence.
 */
#[Fillable([
    'catalog_item_id', 'description', 'quantity', 'unit', 'unit_price_cents',
    'discount_cents', 'total_cents', 'position',
])]
class ProposalItem extends Model
{
    protected function casts(): array
    {
        return [
            // "decimal:3" mantém a quantidade como STRING exata ("1.500"), nunca float.
            'quantity' => 'decimal:3',
            'unit_price_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class)->withTrashed();
    }

    /**
     * Quantidade formatada no padrão brasileiro, sem zeros desnecessários: "1,5".
     */
    public function formattedQuantity(): string
    {
        $quantity = rtrim(rtrim((string) $this->quantity, '0'), '.');

        return str_replace('.', ',', $quantity);
    }
}
