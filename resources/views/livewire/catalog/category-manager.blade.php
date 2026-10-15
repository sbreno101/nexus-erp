<div>
    <x-ui.page-header title="Categorias do catálogo" subtitle="Organize produtos e serviços em categorias. O nome é único por tipo." :back="route('catalog.index')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Nova categoria">
            <form wire:submit="create" class="space-y-4">
                <x-form.select name="type" label="Tipo" :options="$typeOptions" wire:model="type" required />
                <x-form.input name="name" label="Nome" wire:model="name" maxlength="100" required />
                <div class="flex justify-end">
                    <x-ui.button icon="plus" wire:loading.attr="disabled" wire:target="create">Adicionar</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <div class="space-y-6 lg:col-span-2">
            @foreach ($typeOptions as $typeValue => $typeLabel)
                <x-ui.card :title="$typeValue === 'product' ? 'Categorias de produtos' : 'Categorias de serviços'" :padding="false" wire:key="category-group-{{ $typeValue }}">
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($categoriesByType->get($typeValue, collect()) as $category)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3" wire:key="category-{{ $category->id }}">
                                @if ($editingId === $category->id)
                                    <form wire:submit="rename" class="flex flex-1 flex-wrap items-start gap-2">
                                        <div class="min-w-48 flex-1">
                                            <input type="text" wire:model="editingName" maxlength="100" required aria-label="Novo nome da categoria"
                                                @class(['form-control', 'form-control-invalid' => $errors->has('editingName')])
                                                x-init="$el.focus()" x-on:keydown.escape="$wire.cancelEditing()">
                                            @error('editingName') <p class="form-error">{{ $message }}</p> @enderror
                                        </div>
                                        <x-ui.button size="sm" icon="check">Salvar</x-ui.button>
                                        <x-ui.button type="button" size="sm" variant="secondary" wire:click="cancelEditing">Cancelar</x-ui.button>
                                    </form>
                                @else
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $category->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $category->items_count }} {{ $category->items_count === 1 ? 'item' : 'itens' }}</p>
                                    </div>
                                    <div class="flex gap-1">
                                        <button type="button" wire:click="startEditing({{ $category->id }})" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Renomear"><x-icon name="pencil" class="size-4" /></button>
                                        <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="Excluir a categoria {{ $category->name }}?" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Excluir"><x-icon name="trash" class="size-4" /></button>
                                    </div>
                                @endif
                            </li>
                        @empty
                            <li><x-ui.empty-state icon="squares" title="Nenhuma categoria de {{ mb_strtolower($typeLabel) }}" /></li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</div>
