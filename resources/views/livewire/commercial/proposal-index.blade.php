<div>
    <x-ui.page-header title="Propostas" subtitle="Propostas comerciais e seu ciclo de aprovação.">
        @can('create', \App\Models\Proposal::class)
            <x-ui.button icon="plus" :href="route('proposals.create')">Nova proposta</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Número, título ou cliente..." />
            <select wire:model.live="status" class="form-control w-auto" aria-label="Status">
                <option value="">Todos os status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="owner" class="form-control w-auto" aria-label="Responsável">
                <option value="">Todos os responsáveis</option>
                <option value="me">Minhas</option>
                @foreach ($owners as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="from" class="form-control w-auto" aria-label="Emitidas a partir de">
            <input type="date" wire:model.live="to" class="form-control w-auto" aria-label="Emitidas até">
            <span class="ml-auto text-sm text-slate-500">{{ $summary->total }} propostas · {{ money((int) $summary->amount) }}</span>
        </div>

        @if ($proposals->isEmpty())
            <x-ui.empty-state icon="document-text" title="Nenhuma proposta encontrada" />
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="number" :$sortField :$sortDirection>Número</x-table.sort-header>
                            <th>Cliente / título</th>
                            <th>Responsável</th>
                            <x-table.sort-header field="issue_date" :$sortField :$sortDirection>Emissão</x-table.sort-header>
                            <x-table.sort-header field="valid_until" :$sortField :$sortDirection>Validade</x-table.sort-header>
                            <x-table.sort-header field="total_cents" :$sortField :$sortDirection align="right">Total</x-table.sort-header>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($proposals as $proposal)
                            <tr wire:key="proposal-{{ $proposal->id }}">
                                <td><a href="{{ route('proposals.show', $proposal) }}" class="link tabular-nums">{{ $proposal->number }}</a></td>
                                <td class="max-w-xs !whitespace-normal">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $proposal->customer->display_name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $proposal->title }}</p>
                                </td>
                                <td>{{ $proposal->owner?->name ?? '—' }}</td>
                                <td>{{ br_date($proposal->issue_date) }}</td>
                                <td @class(['font-semibold text-red-600' => $proposal->status === \App\Enums\ProposalStatus::Sent && $proposal->isPastValidity()])>{{ br_date($proposal->valid_until) }}</td>
                                <td class="text-right tabular-nums">{{ money($proposal->total_cents) }}</td>
                                <td><x-ui.badge :color="$proposal->status->color()">{{ $proposal->status->label() }}</x-ui.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $proposals->links() }}</div>
        @endif
    </div>
</div>
