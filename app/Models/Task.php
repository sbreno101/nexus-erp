<?php

namespace App\Models;

use App\Enums\Priority;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tarefa de acompanhamento (ex.: "Retornar ligação para o cliente na sexta").
 */
#[Fillable(['title', 'description', 'priority', 'due_at', 'assigned_to', 'created_by', 'completed_at', 'completed_by'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted() && $this->due_at !== null && $this->due_at->isPast();
    }
}
