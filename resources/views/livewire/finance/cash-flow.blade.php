<div>
    <x-ui.page-header title="Fluxo de caixa" subtitle="Realizado (extrato das contas) x previsto (títulos em aberto por vencimento).">
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="account" class="form-control w-auto" aria-label="Conta financeira">
                <option value="">Todas as contas (consolidado)</option>
                @foreach ($accounts as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="from" class="form-control w-auto" aria-label="Data inicial" value="{{ $filters['de'] }}">
            <input type="date" wire:model.live="to" class="form-control w-auto" aria-label="Data final" value="{{ $filters['ate'] }}">
            @can('reports.financial')
                <x-ui.button variant="secondary" icon="arrow-down-tray" :href="route('reports.export', ['report' => 'fluxo-de-caixa', 'format' => 'csv', 'de' => $filters['de'], 'ate' => $filters['ate'], 'conta' => $filters['conta']])">CSV</x-ui.button>
            @endcan
        </div>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($summary as $card)
            <x-ui.stat :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :color="$card['color'] ?? 'brand'" :hint="$card['hint'] ?? null" />
        @endforeach
    </div>

    <x-ui.card title="Evolução" description="Barras: entradas/saídas realizadas e previstas · Linha: saldo acumulado realizado" class="mb-6">
        <x-chart :config="$chart" height="h-80" />
    </x-ui.card>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Detalhamento" class="lg:col-span-2" :padding="false">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr><th>Período</th><th class="text-right">Entradas</th><th class="text-right">Saídas</th><th class="text-right">Resultado</th><th class="text-right">Saldo</th><th class="text-right">Previsto (líq.)</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['period'] }}</td>
                                <td class="text-right tabular-nums text-emerald-600">{{ money($row['inflow_cents']) }}</td>
                                <td class="text-right tabular-nums text-red-600">{{ money($row['outflow_cents']) }}</td>
                                <td @class(['text-right tabular-nums font-medium', 'text-red-600' => $row['net_cents'] < 0])>{{ money($row['net_cents']) }}</td>
                                <td class="text-right tabular-nums">{{ money($row['balance_cents']) }}</td>
                                <td @class(['text-right tabular-nums', 'text-red-600' => $row['forecast_net_cents'] < 0])>{{ money($row['forecast_net_cents']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state title="Sem movimentação no período" /></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t border-slate-200 font-semibold dark:border-slate-700">
                        <tr>
                            <td class="px-4 py-3">{{ $totals['_label'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money($totals['inflow_cents']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money($totals['outflow_cents']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money($totals['net_cents']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money($totals['balance_cents']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ money($totals['forecast_net_cents']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card title="Saldo por conta" :padding="false">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($accounts as $item)
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <a href="{{ route('accounts.show', $item) }}" @class(['link', 'opacity-60' => ! $item->is_active])>{{ $item->name }}</a>
                            <span @class(['font-medium tabular-nums', 'text-red-600' => $item->current_balance_cents < 0])>{{ money($item->current_balance_cents) }}</span>
                        </li>
                    @endforeach
                    <li class="flex items-center justify-between bg-slate-50 px-5 py-3 text-sm font-semibold dark:bg-slate-800/40">
                        <span>Consolidado (ativas)</span>
                        <span class="tabular-nums">{{ money((int) $accounts->where('is_active', true)->sum('current_balance_cents')) }}</span>
                    </li>
                </ul>
            </x-ui.card>

            <x-ui.card title="Próximos 15 dias" :padding="false">
                <div class="px-5 pt-4 text-xs font-semibold uppercase tracking-wide text-emerald-600">A receber</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($upcomingReceivables as $bill)
                        <li class="flex justify-between gap-2 px-5 py-2 text-sm">
                            <a href="{{ route('receivables.show', $bill) }}" class="link truncate">{{ $bill->customer->display_name }}</a>
                            <span @class(['shrink-0 tabular-nums', 'text-red-600' => $bill->isOverdue()])>{{ $bill->due_date->format('d/m') }} · {{ money($bill->outstandingCents()) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-2 text-sm text-slate-500">Nada a receber.</li>
                    @endforelse
                </ul>
                <div class="px-5 pt-4 text-xs font-semibold uppercase tracking-wide text-red-600">A pagar</div>
                <ul class="divide-y divide-slate-100 pb-2 dark:divide-slate-800">
                    @forelse ($upcomingPayables as $bill)
                        <li class="flex justify-between gap-2 px-5 py-2 text-sm">
                            <a href="{{ route('payables.show', $bill) }}" class="link truncate">{{ $bill->description }}</a>
                            <span @class(['shrink-0 tabular-nums', 'text-red-600' => $bill->isOverdue()])>{{ $bill->due_date->format('d/m') }} · {{ money($bill->outstandingCents()) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-2 text-sm text-slate-500">Nada a pagar.</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>
</div>
