<?php

namespace App\Models;

use App\Enums\InteractionType;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\InteractionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Interação no histórico de relacionamento (cliente, lead ou oportunidade).
 */
#[Fillable(['user_id', 'type', 'subject', 'description', 'occurred_at'])]
class Interaction extends Model
{
    /** @use HasFactory<InteractionFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function interactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
