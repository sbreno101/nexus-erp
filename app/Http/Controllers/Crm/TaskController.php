<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreTaskRequest;
use App\Models\Task;
use App\Support\MorphTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Tarefas de acompanhamento (com data de retorno), vinculadas ou não a um
 * cliente, lead ou oportunidade.
 */
class TaskController extends Controller
{
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $attributes = [
            ...$request->safe()->only(['title', 'description', 'priority', 'due_at']),
            'assigned_to' => $request->input('assigned_to') ?: Auth::id(),
            'created_by' => Auth::id(),
        ];

        if ($request->filled('taskable_type')) {
            $target = MorphTarget::resolve($request->string('taskable_type')->toString(), $request->integer('taskable_id'), StoreTaskRequest::TYPES);
            $this->authorize('attach', $target);
            $target->tasks()->create($attributes);
        } else {
            Task::create($attributes);
        }

        return back()->with('success', 'Tarefa criada.');
    }

    public function toggle(Task $task): RedirectResponse
    {
        $this->authorizeTask($task);

        $task->forceFill([
            'completed_at' => $task->isCompleted() ? null : now(),
            'completed_by' => $task->isCompleted() ? null : Auth::id(),
        ])->save();

        return back()->with('success', $task->isCompleted() ? 'Tarefa concluída.' : 'Tarefa reaberta.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorizeTask($task);
        $task->delete();

        return back()->with('success', 'Tarefa excluída.');
    }

    /**
     * Pode alterar a tarefa: o responsável, o criador ou quem pode editar o registro vinculado.
     */
    private function authorizeTask(Task $task): void
    {
        $user = Auth::user();

        $allowed = in_array($user->id, [$task->assigned_to, $task->created_by], true)
            || ($task->taskable !== null && $user->can('attach', $task->taskable))
            || $user->isSuperAdmin();

        abort_unless($allowed, 403);
    }
}
