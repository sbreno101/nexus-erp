<div>
    <x-ui.page-header title="Catálogo" subtitle="Produtos e serviços usados em propostas, contratos e ordens de serviço.">
        @can('catalog.update')
            <x-ui.button variant="secondary" icon="squares" :href="route('catalog.categories')">Categorias</x-ui.button>
        @endcan
        @can('create', \App\Models\CatalogItem::class)
            <x-ui.button icon="plus" :href="route('catalog.create')">Novo item</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />

        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar por código, nome ou descrição..." />
            <select wire:model.live="type" class="form-control w-auto" aria-label="Filtrar por tipo">
                <option value="">Produtos e serviços</option>
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="category" class="form-control w-auto" aria-label="Filtrar por categoria">
                <option value="">Todas as categorias</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption->id }}" wire:key="category-option-{{ $categoryOption->id }}">
                        {{ $categoryOption->name }}{{ $type === '' ? ' ('.$categoryOption->type->label().')' : '' }}
                    </option>
                @endforeach
            </select>
            <select wire:model.live="situation" class="form-control w-auto" aria-label="Filtrar por situação">
                <option value="">Ativos e inativos</option>
                <option value="ativo">Somente ativos</option>
                <option value="inativo">Somente inativos</option>
            </select>
            @if ($search || $type || $category || $situation)
                <button type="button" wire:click="clearFilters" class="text-sm link">Limpar filtros</button>
            @endif
        </div>

        @if ($items->isEmpty())
            <x-ui.empty-state icon="cube" title="Nenhum item encontrado" description="Ajuste os filtros ou cadastre um novo produto ou serviço.">
                @can('create', \App\Models\CatalogItem::class)
                    <x-ui.button icon="plus" size="sm" :href="route('catalog.create')">Cadastrar item</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="code" :$sortField :$sortDirection>Código</x-table.sort-header>
                            <x-table.sort-header field="name" :$sortField :$sortDirection>Nome</x-table.sort-header>
                            <th>Tipo</th>
                            <th>Unidade</th>
                            <x-table.sort-header field="price_cents" :$sortField :$sortDirection align="right">Preço</x-table.sort-header>
                            <th>Situação</th>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($items as $item)
                            <tr wire:key="catalog-item-{{ $item->id }}">
                                <td class="font-mono text-xs">{{ $item->code }}</td>
                                <td>
                                    <a href="{{ route('catalog.show', $item) }}" class="link">{{ $item->name }}</a>
                                    <p class="text-xs text-slate-500">{{ $item->category?->name ?? 'Sem categoria' }}</p>
                                </td>
                                <td><x-ui.badge :color="$item->type->color()">{{ $item->type->label() }}</x-ui.badge></td>
                                <td>{{ $item->unit }}</td>
                                <td class="text-right tabular-nums">{{ money($item->price_cents) }}</td>
                                <td>
                                    @if ($item->is_active)
                                        <x-ui.badge color="green">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge color="gray">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        @can('update', $item)
                                            <a href="{{ route('catalog.edit', $item) }}" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                        @endcan
                                        @can('delete', $item)
                                            <button type="button" wire:click="delete({{ $item->id }})" wire:confirm="Excluir o item {{ $item->code }} — {{ $item->name }}?" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Excluir"><x-icon name="trash" class="size-4" /></button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $items->links() }}</div>
        @endif
    </div>
</div>
