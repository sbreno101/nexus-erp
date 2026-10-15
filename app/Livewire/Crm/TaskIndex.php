<?php

namespace App\Livewire\Crm;

use App\Enums\Priority;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Minhas tarefas": retornos e próximos passos atribuídos ao usuário.
 */
#[Title('Tarefas')]
class TaskIndex extends Component
{
    use WithDataTable;

    /**
     * pending | overdue | today | done | all
     */
    #[Url(as: 'situacao', except: 'pending')]
    public string $situation = 'pending';

    #[Url(as: 'prioridade', except: '')]
    public string $priority = '';

    public string $newTitle = '';

    public string $newDueAt = '';

    public string $newPriority = 'medium';

    public function mount(): void
    {
        $this->authorize('dashboard.view');
    }

    public function toggle(int $taskId): void
    {
        $task = Task::query()->where('assigned_to', Auth::id())->findOrFail($taskId);

        $task->forceFill([
            'completed_at' => $task->isCompleted() ? null : now(),
            'completed_by' => $task->isCompleted() ? null : Auth::id(),
        ])->save();
    }

    public function create(): void
    {
        $validated = $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
            'newDueAt' => ['nullable', 'date'],
            'newPriority' => ['required', 'in:'.implode(',', Priority::values())],
        ], attributes: ['newTitle' => 'título', 'newDueAt' => 'data de retorno']);

        Task::create([
            'title' => $validated['newTitle'],
            'due_at' => $validated['newDueAt'] ?: null,
            'priority' => $validated['newPriority'],
            'assigned_to' => Auth::id(),
            'created_by' => Auth::id(),
        ]);

        $this->reset('newTitle', 'newDueAt', 'newPriority');
        $this->dispatch('notify', type: 'success', message: 'Tarefa criada.');
    }

    public function render(): View
    {
        $query = Task::query()
            ->with('taskable')
            ->where('assigned_to', Auth::id())
            ->when(trim($this->search) !== '', fn ($query) => $query->where('title', 'ilike', '%'.trim($this->search).'%'))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->when($this->situation === 'pending', fn ($query) => $query->whereNull('completed_at'))
            ->when($this->situation === 'overdue', fn ($query) => $query->whereNull('completed_at')->where('due_at', '<', now()))
            ->when($this->situation === 'today', fn ($query) => $query->whereNull('completed_at')->whereDate('due_at', today()))
            ->when($this->situation === 'done', fn ($query) => $query->whereNotNull('completed_at'))
            ->orderByRaw('completed_at IS NOT NULL')
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at');

        return view('livewire.crm.task-index', [
            'tasks' => $query->paginate(20),
            'priorityOptions' => Priority::options(),
        ]);
    }

    /**
     * Link para o registro vinculado à tarefa (cliente, lead ou oportunidade).
     */
    public function linkFor(Task $task): ?string
    {
        return match ($task->taskable_type) {
            'customer' => route('customers.show', $task->taskable_id),
            'lead' => route('leads.show', $task->taskable_id),
            'opportunity' => route('opportunities.show', $task->taskable_id),
            default => null,
        };
    }
}
