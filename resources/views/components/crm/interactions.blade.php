{{-- Linha do tempo de interações + formulário para registrar uma nova. --}}
@props(['model', 'interactions'])

@php($canAdd = auth()->user()->can('attach', $model))

<x-ui.card title="Histórico de interações" :padding="false">
    @if ($canAdd)
        <form method="POST" action="{{ route('interactions.store') }}" class="space-y-3 border-b border-slate-200 p-5 dark:border-slate-800">
            @csrf
            <input type="hidden" name="interactable_type" value="{{ $model->getMorphClass() }}">
            <input type="hidden" name="interactable_id" value="{{ $model->getKey() }}">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-form.select name="type" label="Tipo" :options="\App\Enums\InteractionType::options()" value="call" required />
                <x-form.input name="occurred_at" type="datetime-local" label="Data" :value="now()->format('Y-m-d\TH:i')" required />
                <x-form.input name="subject" label="Assunto" maxlength="255" />
            </div>
            <x-form.textarea name="description" label="Descrição" rows="2" required maxlength="5000" placeholder="O que foi conversado, próximos passos..." />
            <div class="flex justify-end">
                <x-ui.button size="sm" icon="plus">Registrar interação</x-ui.button>
            </div>
        </form>
    @endif

    <ol class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse ($interactions as $interaction)
            <li class="flex gap-3 px-5 py-4">
                <x-ui.badge :color="$interaction->type->color()" class="h-fit">{{ $interaction->type->label() }}</x-ui.badge>
                <div class="min-w-0 flex-1">
                    @if ($interaction->subject)
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $interaction->subject }}</p>
                    @endif
                    <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $interaction->description }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ br_date($interaction->occurred_at, true) }} · {{ $interaction->user?->name ?? 'Sistema' }}</p>
                </div>
                @if ($interaction->user_id === auth()->id())
                    <form method="POST" action="{{ route('interactions.destroy', $interaction) }}" x-data x-on:submit="if (! confirm('Excluir esta interação?')) $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-slate-400 hover:text-red-600" aria-label="Excluir interação"><x-icon name="trash" class="size-4" /></button>
                    </form>
                @endif
            </li>
        @empty
            <li><x-ui.empty-state icon="chat-bubble" title="Nenhuma interação registrada" description="Registre ligações, reuniões e anotações para manter o histórico do relacionamento." /></li>
        @endforelse
    </ol>
</x-ui.card>
