{{-- Tarefas de acompanhamento vinculadas a um registro. --}}
@props(['model', 'tasks', 'users' => collect()])

@php($canAdd = auth()->user()->can('attach', $model))

<x-ui.card title="Tarefas e retornos" :padding="false">
    @if ($canAdd)
        <x-slot:actions>
            <x-ui.button type="button" size="sm" variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'new-task')">Nova tarefa</x-ui.button>
        </x-slot:actions>
    @endif

    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse ($tasks as $task)
            <li class="flex items-start gap-3 px-5 py-3">
                <form method="POST" action="{{ route('tasks.toggle', $task) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" @class([
                        'mt-0.5 flex size-5 items-center justify-center rounded border',
                        'border-emerald-500 bg-emerald-500 text-white' => $task->isCompleted(),
                        'border-slate-300 hover:border-brand-500 dark:border-slate-600' => ! $task->isCompleted(),
                    ]) aria-label="{{ $task->isCompleted() ? 'Reabrir tarefa' : 'Concluir tarefa' }}">
                        @if ($task->isCompleted()) <x-icon name="check" class="size-3.5" /> @endif
                    </button>
                </form>
                <div class="min-w-0 flex-1">
                    <p @class(['text-sm font-medium', 'text-slate-400 line-through' => $task->isCompleted(), 'text-slate-900 dark:text-white' => ! $task->isCompleted()])>{{ $task->title }}</p>
                    <p class="text-xs text-slate-500">
                        @if ($task->due_at)
                            <span @class(['font-semibold text-red-600' => $task->isOverdue()])>Retorno: {{ br_date($task->due_at, true) }}</span> ·
                        @endif
                        {{ $task->assignee?->name ?? 'Sem responsável' }}
                    </p>
                </div>
                <x-ui.badge :color="$task->priority->color()">{{ $task->priority->label() }}</x-ui.badge>
            </li>
        @empty
            <li><x-ui.empty-state icon="clipboard-list" title="Nenhuma tarefa" description="Agende retornos e próximos passos." /></li>
        @endforelse
    </ul>
</x-ui.card>

@if ($canAdd)
    <x-ui.modal name="new-task" title="Nova tarefa">
        <form method="POST" action="{{ route('tasks.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="taskable_type" value="{{ $model->getMorphClass() }}">
            <input type="hidden" name="taskable_id" value="{{ $model->getKey() }}">
            <x-form.input name="title" label="Título" required maxlength="255" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="due_at" type="datetime-local" label="Data de retorno" />
                <x-form.select name="priority" label="Prioridade" :options="\App\Enums\Priority::options()" value="medium" required />
            </div>
            <x-form.select name="assigned_to" label="Responsável" :options="$users->pluck('name', 'id')->all()" :value="auth()->id()" placeholder="Eu mesmo" />
            <x-form.textarea name="description" label="Detalhes" rows="2" />
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'new-task')">Cancelar</x-ui.button>
                <x-ui.button>Criar tarefa</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endif
