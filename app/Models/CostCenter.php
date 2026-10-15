<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Centro de custo (ex.: "ADM - Administrativo", "OPS - Operações").
 */
#[Fillable(['code', 'name', 'is_active'])]
class CostCenter extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Payable, $this>
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    public function label(): string
    {
        return "{$this->code} - {$this->name}";
    }
}
