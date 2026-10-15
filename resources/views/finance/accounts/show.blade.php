<x-layouts::app :title="$account->name">
    <x-ui.page-header :title="$account->name" :subtitle="$account->type->label().($account->bankDescription() ? ' · '.$account->bankDescription() : '')" :back="route('accounts.index')">
        @can('update', $account)
            <x-ui.button variant="secondary" icon="plus" type="button" x-data x-on:click="$dispatch('open-modal', 'manual-transaction')">Lançamento avulso</x-ui.button>
            <x-ui.button variant="secondary" icon="pencil" :href="route('accounts.edit', $account)">Editar</x-ui.button>
        @endcan
    </x-ui.page-header>

    @unless ($ledgerMatches)
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-500/10 dark:text-red-300" role="alert">
            <strong>Atenção:</strong> o saldo registrado não confere com o extrato. Execute <code>php artisan finance:verify-balances</code> e verifique alterações feitas diretamente no banco.
        </div>
    @endunless

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Saldo atual" :value="money($account->current_balance_cents)" icon="building-library" color="blue" />
        <x-ui.stat label="Saldo anterior ao período" :value="money($previousBalance)" icon="clock" />
        <x-ui.stat label="Entradas no período" :value="money($inflow)" icon="arrow-trending-up" color="green" />
        <x-ui.stat label="Saídas no período" :value="money($outflow)" icon="arrow-trending-down" color="red" />
    </div>

    <x-ui.card title="Extrato" :padding="false">
        <x-slot:actions>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="date" name="de" value="{{ $from->toDateString() }}" class="form-control w-auto" aria-label="Data inicial">
                <input type="date" name="ate" value="{{ $to->toDateString() }}" class="form-control w-auto" aria-label="Data final">
                <x-ui.button size="sm" variant="secondary" icon="magnifying-glass">Filtrar</x-ui.button>
            </form>
        </x-slot:actions>

        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Data</th><th>Descrição</th><th>Tipo</th><th class="text-right">Valor</th><th class="text-right">Saldo</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr class="bg-slate-50 dark:bg-slate-800/40"><td>{{ $from->format('d/m/Y') }}</td><td colspan="3" class="text-slate-500">Saldo anterior</td><td class="text-right font-medium tabular-nums">{{ money($previousBalance) }}</td><td></td></tr>
                    @forelse ($statement as $row)
                        @php($transaction = $row['transaction'])
                        <tr>
                            <td>{{ br_date($transaction->occurred_on) }}</td>
                            <td class="max-w-md !whitespace-normal">
                                {{ $transaction->description }}
                                <p class="text-xs text-slate-500">
                                    {{ $transaction->category?->name }}
                                    @if ($transaction->payment?->receivable) · <a href="{{ route('receivables.show', $transaction->payment->receivable) }}" class="link">título a receber</a> @endif
                                    @if ($transaction->payment?->payable) · <a href="{{ route('payables.show', $transaction->payment->payable) }}" class="link">título a pagar</a> @endif
                                    · {{ $transaction->user?->name ?? 'Sistema' }}
                                </p>
                            </td>
                            <td><x-ui.badge :color="$transaction->direction->color()">{{ $transaction->type->label() }}</x-ui.badge></td>
                            <td @class(['text-right font-medium tabular-nums', 'text-emerald-600' => $transaction->direction->value === 'in', 'text-red-600' => $transaction->direction->value === 'out'])>
                                {{ $transaction->direction->value === 'out' ? '-' : '+' }} {{ money($transaction->amount_cents) }}
                            </td>
                            <td class="text-right tabular-nums">{{ money($row['balance']) }}</td>
                            <td class="text-right">
                                @if ($transaction->type === \App\Enums\TransactionType::Manual && $transaction->reversal === null && auth()->user()->can('update', $account))
                                    <x-ui.confirm-action :action="route('accounts.transactions.reverse', $transaction)" method="PATCH" title="Estornar lançamento"
                                        message="Um lançamento inverso será registrado. O lançamento original permanece no extrato." confirm="Estornar" icon="arrow-uturn-left" size="sm" reason reason-label="Motivo">Estornar</x-ui.confirm-action>
                                @elseif ($transaction->reversal)
                                    <span class="text-xs text-slate-400">estornado</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state icon="banknotes" title="Nenhum lançamento no período" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    @can('update', $account)
        <x-ui.modal name="manual-transaction" title="Lançamento avulso">
            <form method="POST" action="{{ route('accounts.transactions.store', $account) }}" class="space-y-4">
                @csrf
                <p class="text-sm text-slate-600 dark:text-slate-300">Use para movimentos sem título associado: tarifas, rendimentos, aportes, ajustes.</p>
                <x-form.select name="direction" label="Tipo" :options="\App\Enums\TransactionDirection::options()" value="out" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.money name="amount" label="Valor" required />
                    <x-form.input name="occurred_on" type="date" label="Data" :value="today()->toDateString()" required />
                </div>
                <x-form.input name="description" label="Descrição" required maxlength="255" />
                <x-form.select name="financial_category_id" label="Categoria" :options="$categories" placeholder="Sem categoria" />
                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'manual-transaction')">Cancelar</x-ui.button>
                    <x-ui.button icon="check">Lançar</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
        @if ($errors->hasAny(['direction', 'amount', 'amount_cents', 'occurred_on', 'description']))
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'manual-transaction'))"></div>
        @endif
    @endcan
</x-layouts::app>
