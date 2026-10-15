<?php

namespace App\Models;

use App\Enums\FinancialCategoryType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria financeira (plano de contas simplificado): receita ou despesa.
 */
#[Fillable(['name', 'type', 'is_active'])]
class FinancialCategory extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => FinancialCategoryType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Receivable, $this>
     */
    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    /**
     * @return HasMany<Payable, $this>
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    /**
     * @param  Builder<FinancialCategory>  $query
     */
    public function scopeOfType(Builder $query, FinancialCategoryType $type): void
    {
        $query->where('type', $type)->where('is_active', true)->orderBy('name');
    }
}
