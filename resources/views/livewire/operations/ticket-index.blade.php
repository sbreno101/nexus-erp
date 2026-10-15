<div>
    <x-ui.page-header title="Chamados" subtitle="Atendimento aos clientes: solicitações, dúvidas e problemas.">
        @can('tickets.update')
            <x-ui.button variant="secondary" icon="cog" :href="route('tickets.categories')">Categorias</x-ui.button>
        @endcan
        @can('create', \App\Models\Ticket::class)
            <x-ui.button icon="plus" :href="route('tickets.create')">Novo chamado</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />

        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar por número, assunto ou cliente..." />
            <select wire:model.live="status" class="form-control w-auto" aria-label="Filtrar por status">
                <option value="">Todos os status</option>
                <option value="{{ \App\Livewire\Operations\TicketIndex::OPEN_FILTER }}">Em aberto (não finalizados)</option>
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
            <select wire:model.live="category" class="form-control w-auto" aria-label="Filtrar por categoria">
                <option value="">Todas as categorias</option>
                @foreach ($categoryOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="assignee" class="form-control w-auto" aria-label="Filtrar por responsável">
                <option value="">Todos os responsáveis</option>
                <option value="{{ \App\Livewire\Operations\TicketIndex::MINE_FILTER }}">Meus</option>
                <option value="{{ \App\Livewire\Operations\TicketIndex::UNASSIGNED_FILTER }}">Sem responsável</option>
                @foreach ($users as $userId => $userName)
                    <option value="{{ $userId }}">{{ $userName }}</option>
                @endforeach
            </select>
            @if ($search || $status || $priority || $category || $assignee)
                <button type="button" wire:click="clearFilters" class="text-sm link">Limpar filtros</button>
            @endif
        </div>

        @if ($tickets->isEmpty())
            <x-ui.empty-state icon="lifebuoy" title="Nenhum chamado encontrado" description="Ajuste os filtros ou abra um novo chamado.">
                @can('create', \App\Models\Ticket::class)
                    <x-ui.button icon="plus" size="sm" :href="route('tickets.create')">Abrir chamado</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="number" :$sortField :$sortDirection>Chamado</x-table.sort-header>
                            <th>Cliente</th>
                            <th>Categoria</th>
                            <th>Canal</th>
                            <th>Responsável</th>
                            <x-table.sort-header field="priority" :$sortField :$sortDirection>Prioridade</x-table.sort-header>
                            <th>Status</th>
                            <x-table.sort-header field="created_at" :$sortField :$sortDirection>Aberto em</x-table.sort-header>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($tickets as $ticket)
                            <tr wire:key="ticket-{{ $ticket->id }}">
                                <td>
                                    <a href="{{ route('tickets.show', $ticket) }}" class="link font-medium">{{ $ticket->number }}</a>
                                    <p class="max-w-xs truncate text-xs text-slate-500">{{ $ticket->subject }}</p>
                                </td>
                                <td>{{ $ticket->customer?->display_name ?? '—' }}</td>
                                <td>{{ $ticket->category?->name ?? '—' }}</td>
                                <td>{{ $ticket->channel->label() }}</td>
                                <td>{{ $ticket->assignee?->name ?? '—' }}</td>
                                <td><x-ui.badge :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</x-ui.badge></td>
                                <td><x-ui.badge :color="$ticket->status->color()">{{ $ticket->status->label() }}</x-ui.badge></td>
                                <td class="whitespace-nowrap tabular-nums text-slate-500">{{ br_date($ticket->created_at, true) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $tickets->links() }}</div>
        @endif
    </div>
</div>
