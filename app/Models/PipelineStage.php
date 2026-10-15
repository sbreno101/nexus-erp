<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Etapa configurável do funil comercial (colunas do Kanban).
 *
 * Etapas marcadas como "ganho" ou "perda" encerram a oportunidade quando
 * ela é movida para lá.
 */
#[Fillable(['name', 'position', 'probability', 'color', 'is_won', 'is_lost'])]
class PipelineStage extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'probability' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * @param  Builder<PipelineStage>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function isClosing(): bool
    {
        return $this->is_won || $this->is_lost;
    }
}
