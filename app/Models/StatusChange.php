<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Uma mudança de status registrada (quem, quando, de/para e motivo).
 */
#[Fillable(['company_id', 'from_status', 'to_status', 'user_id', 'reason'])]
class StatusChange extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function statusable(): MorphTo
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

    /**
     * Converte o valor bruto do status no rótulo do Enum do registro "dono".
     */
    public function labelFor(?string $status, string $enumClass): string
    {
        if ($status === null) {
            return '—';
        }

        return $enumClass::tryFrom($status)?->label() ?? $status;
    }
}
