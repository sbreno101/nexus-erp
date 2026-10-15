<div>
    <x-ui.page-header title="Fornecedores" subtitle="Empresas e profissionais que fornecem produtos e serviços.">
        @can('export', \App\Models\Supplier::class)
            <x-ui.button variant="secondary" icon="arrow-down-tray" :href="route('suppliers.export', ['busca' => $search, 'status' => $status])">Exportar CSV</x-ui.button>
        @endcan
        @can('create', \App\Models\Supplier::class)
            <x-ui.button icon="plus" :href="route('suppliers.create')">Novo fornecedor</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar por nome, e-mail ou CNPJ..." />
            <select wire:model.live="status" class="form-control w-auto" aria-label="Filtrar por status">
                <option value="">Todos os status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($suppliers->isEmpty())
            <x-ui.empty-state icon="truck" title="Nenhum fornecedor encontrado" />
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="name" :$sortField :$sortDirection>Fornecedor</x-table.sort-header>
                            <x-table.sort-header field="document" :$sortField :$sortDirection>CPF/CNPJ</x-table.sort-header>
                            <th>Contato</th>
                            <th class="text-right">Em aberto</th>
                            <th>Status</th>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($suppliers as $supplier)
                            <tr wire:key="supplier-{{ $supplier->id }}">
                                <td><a href="{{ route('suppliers.show', $supplier) }}" class="link">{{ $supplier->display_name }}</a></td>
                                <td class="tabular-nums">{{ $supplier->formatted_document }}</td>
                                <td>{{ $supplier->email ?? '—' }}<p class="text-xs text-slate-500">{{ $supplier->phone }}</p></td>
                                <td class="text-right tabular-nums">{{ money((int) $supplier->open_cents) }}</td>
                                <td><x-ui.badge :color="$supplier->status->color()">{{ $supplier->status->label() }}</x-ui.badge></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        @can('update', $supplier)
                                            <a href="{{ route('suppliers.edit', $supplier) }}" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                        @endcan
                                        @can('delete', $supplier)
                                            <button type="button" wire:click="delete({{ $supplier->id }})" wire:confirm="Excluir o fornecedor {{ $supplier->display_name }}?" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Excluir"><x-icon name="trash" class="size-4" /></button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $suppliers->links() }}</div>
        @endif
    </div>
</div>
