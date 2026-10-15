<?php

namespace App\Models\Concerns;

use App\Models\Interaction;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Histórico de interações (ligações, reuniões, anotações) e tarefas de
 * acompanhamento — usado por clientes, leads e oportunidades.
 *
 * @mixin Model
 */
trait HasRelationshipHistory
{
    /**
     * @return MorphMany<Interaction, $this>
     */
    public function interactions(): MorphMany
    {
        return $this->morphMany(Interaction::class, 'interactable')->latest('occurred_at');
    }

    /**
     * @return MorphMany<Task, $this>
     */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable')->orderByRaw('completed_at IS NOT NULL')->orderBy('due_at');
    }
}
