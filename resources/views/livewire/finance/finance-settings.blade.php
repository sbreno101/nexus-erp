<div>
    <x-ui.page-header title="Configurações financeiras" subtitle="Plano de contas simplificado (categorias) e centros de custo." :back="route('accounts.index')" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Categorias financeiras" :padding="false">
            @foreach (['income' => 'Receitas', 'expense' => 'Despesas'] as $type => $label)
                <p class="border-b border-slate-100 bg-slate-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-800/40">{{ $label }}</p>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($categories->get($type, collect()) as $category)
                        <li wire:key="category-{{ $category->id }}" class="flex items-center justify-between gap-2 px-5 py-2.5">
                            <div>
                                <p @class(['text-sm', 'text-slate-800 dark:text-slate-100' => $category->is_active, 'text-slate-400 line-through' => ! $category->is_active])>{{ $category->name }}</p>
                                <p class="text-xs text-slate-500">{{ $category->receivables_count + $category->payables_count }} títulos</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="toggleCategory({{ $category->id }})" class="text-xs link">{{ $category->is_active ? 'Inativar' : 'Ativar' }}</button>
                                <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="Excluir a categoria {{ $category->name }}?" class="text-slate-400 hover:text-red-600" aria-label="Excluir"><x-icon name="trash" class="size-4" /></button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endforeach

            <form wire:submit="addCategory" class="flex flex-wrap items-start gap-2 border-t border-slate-200 p-4 dark:border-slate-800">
                <select wire:model="categoryType" class="form-control w-auto" aria-label="Tipo">
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div class="min-w-40 flex-1">
                    <input type="text" wire:model="categoryName" class="form-control" placeholder="Nova categoria" aria-label="Nova categoria">
                    @error('categoryName') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <x-ui.button icon="plus">Adicionar</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Centros de custo" :padding="false">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($costCenters as $center)
                    <li wire:key="center-{{ $center->id }}" class="flex items-center justify-between px-5 py-2.5">
                        <div>
                            <p @class(['text-sm', 'text-slate-800 dark:text-slate-100' => $center->is_active, 'text-slate-400 line-through' => ! $center->is_active])>{{ $center->label() }}</p>
                            <p class="text-xs text-slate-500">{{ $center->payables_count }} contas a pagar</p>
                        </div>
                        <button type="button" wire:click="toggleCostCenter({{ $center->id }})" class="text-xs link">{{ $center->is_active ? 'Inativar' : 'Ativar' }}</button>
                    </li>
                @endforeach
            </ul>
            <form wire:submit="addCostCenter" class="flex flex-wrap items-start gap-2 border-t border-slate-200 p-4 dark:border-slate-800">
                <div class="w-28">
                    <input type="text" wire:model="centerCode" class="form-control uppercase" placeholder="Código" aria-label="Código">
                    @error('centerCode') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="min-w-40 flex-1">
                    <input type="text" wire:model="centerName" class="form-control" placeholder="Nome" aria-label="Nome do centro de custo">
                    @error('centerName') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <x-ui.button icon="plus">Adicionar</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</div>
