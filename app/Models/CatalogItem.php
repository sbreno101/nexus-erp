<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\CatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Produto ou serviço do catálogo.
 *
 * O histórico de alterações relevantes (ex.: mudança de preço) vem da
 * auditoria automática (trait Auditable).
 */
#[Fillable(['catalog_category_id', 'type', 'code', 'name', 'description', 'unit', 'price_cents', 'is_active'])]
class CatalogItem extends Model
{
    /** @use HasFactory<CatalogItemFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /**
     * @return HasMany<ProposalItem, $this>
     */
    public function proposalItems(): HasMany
    {
        return $this->hasMany(ProposalItem::class);
    }

    /**
     * @return HasMany<ContractItem, $this>
     */
    public function contractItems(): HasMany
    {
        return $this->hasMany(ContractItem::class);
    }

    /**
     * @param  Builder<CatalogItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<CatalogItem>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->whereAny(['name', 'code', 'description'], 'ilike', "%{$term}%");
        }
    }
}
