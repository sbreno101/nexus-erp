<div>
    <x-ui.page-header title="Leads" subtitle="Contatos comerciais em qualificação, antes de virarem clientes.">
        @can('crm.configure')
            <x-ui.button variant="secondary" icon="cog" :href="route('crm.settings')">Funil e origens</x-ui.button>
        @endcan
        @can('create', \App\Models\Lead::class)
            <x-ui.button icon="plus" :href="route('leads.create')">Novo lead</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar por nome, empresa, e-mail ou telefone..." />
            <select wire:model.live="status" class="form-control w-auto" aria-label="Status">
                <option value="">Todos os status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="source" class="form-control w-auto" aria-label="Origem">
                <option value="">Todas as origens</option>
                @foreach ($sources as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="owner" class="form-control w-auto" aria-label="Responsável">
                <option value="">Todos os responsáveis</option>
                <option value="me">Meus leads</option>
                @foreach ($owners as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        @if ($leads->isEmpty())
            <x-ui.empty-state icon="funnel" title="Nenhum lead encontrado" />
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="name" :$sortField :$sortDirection>Lead</x-table.sort-header>
                            <th>Origem</th>
                            <th>Responsável</th>
                            <x-table.sort-header field="estimated_value_cents" :$sortField :$sortDirection>Valor estimado</x-table.sort-header>
                            <x-table.sort-header field="next_contact_at" :$sortField :$sortDirection>Próximo contato</x-table.sort-header>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($leads as $lead)
                            <tr wire:key="lead-{{ $lead->id }}">
                                <td>
                                    <a href="{{ route('leads.show', $lead) }}" class="link">{{ $lead->name }}</a>
                                    <p class="text-xs text-slate-500">{{ collect([$lead->company_name, $lead->email])->filter()->implode(' · ') }}</p>
                                </td>
                                <td>{{ $lead->source?->name ?? '—' }}</td>
                                <td>{{ $lead->owner?->name ?? '—' }}</td>
                                <td class="tabular-nums">{{ money($lead->estimated_value_cents) }}</td>
                                <td @class(['font-semibold text-red-600' => $lead->next_contact_at?->isPast() && ! $lead->isConverted()])>{{ br_date($lead->next_contact_at) }}</td>
                                <td><x-ui.badge :color="$lead->status->color()">{{ $lead->status->label() }}</x-ui.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $leads->links() }}</div>
        @endif
    </div>
</div>
