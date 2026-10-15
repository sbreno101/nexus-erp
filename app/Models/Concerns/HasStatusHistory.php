<?php

namespace App\Models\Concerns;

use App\Models\StatusChange;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Histórico de mudanças de status (propostas, contratos, OS e chamados).
 *
 * @mixin Model
 */
trait HasStatusHistory
{
    /**
     * @return MorphMany<StatusChange, $this>
     */
    public function statusChanges(): MorphMany
    {
        return $this->morphMany(StatusChange::class, 'statusable')->latest('created_at')->latest('id');
    }

    public function recordStatusChange(BackedEnum|string|null $from, BackedEnum|string $to, ?string $reason = null): StatusChange
    {
        return $this->statusChanges()->create([
            'company_id' => $this->company_id,
            'from_status' => $from instanceof BackedEnum ? $from->value : $from,
            'to_status' => $to instanceof BackedEnum ? $to->value : $to,
            'user_id' => Auth::id(),
            'reason' => $reason,
        ]);
    }
}
