<div>
    <x-ui.page-header title="Oportunidades" subtitle="Visão em tabela do funil comercial.">
        <x-ui.button variant="secondary" icon="view-columns" :href="route('opportunities.pipeline')">Ver Kanban</x-ui.button>
        @can('create', \App\Models\Opportunity::class)
            <x-ui.button icon="plus" :href="route('opportunities.create')">Nova oportunidade</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-4 grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Valor total (filtro atual)" :value="money((int) $totals->total)" icon="banknotes" />
        <x-ui.stat label="Valor ponderado pela probabilidade" :value="money((int) $totals->weighted)" icon="chart-bar" color="purple" />
    </div>

    <div class="card relative">
        <x-table.loading />
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar por título..." />
            <select wire:model.live="status" class="form-control w-auto" aria-label="Status">
                <option value="">Todos os status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="stage" class="form-control w-auto" aria-label="Etapa">
                <option value="">Todas as etapas</option>
                @foreach ($stages as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="owner" class="form-control w-auto" aria-label="Responsável">
                <option value="">Todos os responsáveis</option>
                <option value="me">Minhas</option>
                @foreach ($owners as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        @if ($opportunities->isEmpty())
            <x-ui.empty-state icon="view-columns" title="Nenhuma oportunidade encontrada" />
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="title" :$sortField :$sortDirection>Oportunidade</x-table.sort-header>
                            <th>Etapa</th>
                            <th>Responsável</th>
                            <x-table.sort-header field="estimated_value_cents" :$sortField :$sortDirection>Valor</x-table.sort-header>
                            <x-table.sort-header field="probability" :$sortField :$sortDirection>Prob.</x-table.sort-header>
                            <x-table.sort-header field="expected_close_date" :$sortField :$sortDirection>Previsão</x-table.sort-header>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($opportunities as $opportunity)
                            <tr wire:key="opp-{{ $opportunity->id }}">
                                <td>
                                    <a href="{{ route('opportunities.show', $opportunity) }}" class="link">{{ $opportunity->title }}</a>
                                    <p class="text-xs text-slate-500">{{ $opportunity->counterpartName() }}</p>
                                </td>
                                <td><span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: {{ $opportunity->stage->color }}"></span>{{ $opportunity->stage->name }}</span></td>
                                <td>{{ $opportunity->owner?->name ?? '—' }}</td>
                                <td class="tabular-nums">{{ money($opportunity->estimated_value_cents) }}</td>
                                <td>{{ $opportunity->probability }}%</td>
                                <td>{{ br_date($opportunity->expected_close_date) }}</td>
                                <td><x-ui.badge :color="$opportunity->status->color()">{{ $opportunity->status->label() }}</x-ui.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $opportunities->links() }}</div>
        @endif
    </div>
</div>
