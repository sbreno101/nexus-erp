<div>
    <x-ui.page-header title="Categorias de chamados" subtitle="Organizam os atendimentos e alimentam o relatório de produtividade." :back="route('tickets.index')" />

    <x-ui.card class="max-w-2xl" :padding="false">
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($categories as $category)
                <li wire:key="ticket-category-{{ $category->id }}" class="flex flex-wrap items-center gap-2 px-5 py-3">
                    <div class="min-w-48 flex-1">
                        <input type="text" wire:model="names.{{ $category->id }}" class="form-control" aria-label="Nome da categoria">
                        @error("names.{$category->id}") <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <span class="text-xs text-slate-500">{{ $category->tickets_count }} chamados</span>
                    <x-ui.button type="button" size="sm" variant="secondary" wire:click="rename({{ $category->id }})">Salvar</x-ui.button>
                    <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="Excluir a categoria {{ $category->name }}?" class="rounded p-1.5 text-slate-400 hover:text-red-600" aria-label="Excluir"><x-icon name="trash" class="size-4" /></button>
                </li>
            @empty
                <li><x-ui.empty-state icon="lifebuoy" title="Nenhuma categoria" /></li>
            @endforelse
        </ul>
        <form wire:submit="add" class="flex items-start gap-2 border-t border-slate-200 p-4 dark:border-slate-800">
            <div class="flex-1">
                <input type="text" wire:model="name" class="form-control" placeholder="Nova categoria" aria-label="Nova categoria">
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-ui.button icon="plus">Adicionar</x-ui.button>
        </form>
    </x-ui.card>
</div>
