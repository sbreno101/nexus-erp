<div>
    <x-ui.page-header title="Ordens de serviço" subtitle="Trabalhos em execução para os clientes, com prazos e responsáveis.">
        @can('create', \App\Models\ServiceOrder::class)
            <x-ui.button icon="plus" :href="route('service-orders.create')">Nova OS</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />

        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar por número, título ou cliente..." />
            <select wire:model.live="status" class="form-control w-auto" aria-label="Filtrar por status">
                <option value="">Todos os status</option>
                <option value="{{ \App\Livewire\Operations\ServiceOrderIndex::PENDING_FILTER }}">Pendentes (não finalizadas)</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="priority" class="form-control w-auto" aria-label="Filtrar por prioridade">
                <option value="">Todas as prioridades</option>
                @foreach ($priorityOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="assignee" class="form-control w-auto" aria-label="Filtrar por responsável">
                <option value="">Todos os responsáveis</option>
                <option value="{{ \App\Livewire\Operations\ServiceOrderIndex::MINE_FILTER }}">Minhas</option>
                <option value="{{ \App\Livewire\Operations\ServiceOrderIndex::UNASSIGNED_FILTER }}">Sem responsável</option>
                @foreach ($users as $userId => $userName)
                    <option value="{{ $userId }}">{{ $userName }}</option>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" wire:model.live="onlyOverdue" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                Somente atrasadas
            </label>
            @if ($search || $status || $priority || $assignee || $onlyOverdue)
                <button type="button" wire:click="clearFilters" class="text-sm link">Limpar filtros</button>
            @endif
        </div>

        @if ($serviceOrders->isEmpty())
            <x-ui.empty-state icon="wrench" title="Nenhuma ordem de serviço encontrada" description="Ajuste os filtros ou abra uma nova OS.">
                @can('create', \App\Models\ServiceOrder::class)
                    <x-ui.button icon="plus" size="sm" :href="route('service-orders.create')">Abrir OS</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="number" :$sortField :$sortDirection>OS</x-table.sort-header>
                            <th>Cliente</th>
                            <th>Responsável</th>
                            <x-table.sort-header field="priority" :$sortField :$sortDirection>Prioridade</x-table.sort-header>
                            <x-table.sort-header field="due_date" :$sortField :$sortDirection>Prazo</x-table.sort-header>
                            <th>Status</th>
                            <x-table.sort-header field="created_at" :$sortField :$sortDirection>Aberta em</x-table.sort-header>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($serviceOrders as $serviceOrder)
                            <tr wire:key="service-order-{{ $serviceOrder->id }}">
                                <td>
                                    <a href="{{ route('service-orders.show', $serviceOrder) }}" class="link font-medium">{{ $serviceOrder->number }}</a>
                                    <p class="max-w-xs truncate text-xs text-slate-500">{{ $serviceOrder->title }}</p>
                                </td>
                                <td>{{ $serviceOrder->customer?->display_name ?? '—' }}</td>
                                <td>{{ $serviceOrder->assignee?->name ?? '—' }}</td>
                                <td><x-ui.badge :color="$serviceOrder->priority->color()">{{ $serviceOrder->priority->label() }}</x-ui.badge></td>
                                <td @class(['tabular-nums', 'font-semibold text-red-600 dark:text-red-400' => $serviceOrder->isOverdue()])>
                                    {{ $serviceOrder->due_date ? br_date($serviceOrder->due_date) : '—' }}
                                    @if ($serviceOrder->isOverdue())
                                        <span class="block text-xs font-normal">Atrasada</span>
                                    @endif
                                </td>
                                <td><x-ui.badge :color="$serviceOrder->status->color()">{{ $serviceOrder->status->label() }}</x-ui.badge></td>
                                <td class="tabular-nums text-slate-500">{{ br_date($serviceOrder->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $serviceOrders->links() }}</div>
        @endif
    </div>
</div>
