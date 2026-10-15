<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type'])]
class CatalogCategory extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
        ];
    }

    /**
     * @return HasMany<CatalogItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class);
    }
}
