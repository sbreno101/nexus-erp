<div>
    <x-ui.page-header title="Minhas tarefas" subtitle="Retornos, follow-ups e próximos passos atribuídos a você." />

    <x-ui.card class="mb-6">
        <form wire:submit="create" class="grid items-end gap-3 md:grid-cols-12">
            <div class="md:col-span-6">
                <label class="form-label" for="new-task-title">Nova tarefa</label>
                <input id="new-task-title" type="text" wire:model="newTitle" class="form-control" placeholder="Ex.: Ligar para confirmar a reunião">
                @error('newTitle') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-3">
                <label class="form-label" for="new-task-due">Retorno</label>
                <input id="new-task-due" type="datetime-local" wire:model="newDueAt" class="form-control">
            </div>
            <div class="md:col-span-2">
                <label class="form-label" for="new-task-priority">Prioridade</label>
                <select id="new-task-priority" wire:model="newPriority" class="form-control">
                    @foreach ($priorityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-1"><x-ui.button class="w-full" icon="plus" aria-label="Adicionar"></x-ui.button></div>
        </form>
    </x-ui.card>

    <div class="card relative">
        <x-table.loading />
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar tarefa..." />
            <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                @foreach (['pending' => 'Pendentes', 'today' => 'Hoje', 'overdue' => 'Atrasadas', 'done' => 'Concluídas', 'all' => 'Todas'] as $value => $label)
                    <button type="button" wire:click="$set('situation', '{{ $value }}')" @class(['rounded-md px-3 py-1 text-xs font-medium', 'bg-brand-600 text-white' => $situation === $value, 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => $situation !== $value])>{{ $label }}</button>
                @endforeach
            </div>
            <select wire:model.live="priority" class="form-control w-auto" aria-label="Prioridade">
                <option value="">Todas as prioridades</option>
                @foreach ($priorityOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($tasks as $task)
                <li wire:key="task-{{ $task->id }}" class="flex items-start gap-3 px-5 py-3">
                    <button type="button" wire:click="toggle({{ $task->id }})" @class([
                        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border',
                        'border-emerald-500 bg-emerald-500 text-white' => $task->isCompleted(),
                        'border-slate-300 hover:border-brand-500 dark:border-slate-600' => ! $task->isCompleted(),
                    ]) aria-label="{{ $task->isCompleted() ? 'Reabrir' : 'Concluir' }} tarefa">
                        @if ($task->isCompleted()) <x-icon name="check" class="size-3.5" /> @endif
                    </button>
                    <div class="min-w-0 flex-1">
                        <p @class(['text-sm font-medium', 'text-slate-400 line-through' => $task->isCompleted(), 'text-slate-900 dark:text-white' => ! $task->isCompleted()])>{{ $task->title }}</p>
                        <p class="text-xs text-slate-500">
                            @if ($task->due_at)
                                <span @class(['font-semibold text-red-600' => $task->isOverdue()])>{{ br_date($task->due_at, true) }}</span>
                            @else
                                Sem data
                            @endif
                            @if ($link = $this->linkFor($task))
                                · <a href="{{ $link }}" class="link">{{ config('morph-labels.'.$task->taskable_type) }}: {{ $task->taskable?->display_name ?? $task->taskable?->name ?? $task->taskable?->title }}</a>
                            @endif
                        </p>
                    </div>
                    <x-ui.badge :color="$task->priority->color()">{{ $task->priority->label() }}</x-ui.badge>
                </li>
            @empty
                <li><x-ui.empty-state icon="check-circle" title="Nenhuma tarefa por aqui" description="Tudo em dia!" /></li>
            @endforelse
        </ul>
        @if ($tasks->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $tasks->links() }}</div>
        @endif
    </div>
</div>
