<x-layouts::app title="Contas financeiras">
    <x-ui.page-header title="Contas financeiras" subtitle="Bancos, caixa e carteiras digitais da empresa.">
        @can('finance.configure')
            <x-ui.button variant="secondary" icon="cog" :href="route('finance.settings')">Categorias e centros de custo</x-ui.button>
        @endcan
        @can('viewAny', \App\Models\RecurringExpense::class)
            <x-ui.button variant="secondary" icon="arrow-path" :href="route('recurring-expenses.index')">Despesas recorrentes</x-ui.button>
        @endcan
        @can('transfers.create')
            <x-ui.button variant="secondary" icon="arrows-right-left" :href="route('transfers.create')">Transferir</x-ui.button>
        @endcan
        @can('create', \App\Models\FinancialAccount::class)
            <x-ui.button icon="plus" :href="route('accounts.create')">Nova conta</x-ui.button>
        @endcan
    </x-ui.page-header>

    <x-ui.stat class="mb-6" label="Saldo consolidado (contas ativas)" :value="money($totalBalance)" icon="building-library" color="blue" />

    @if ($accounts->isEmpty())
        <x-ui.card><x-ui.empty-state icon="building-library" title="Nenhuma conta financeira" description="Cadastre as contas bancárias e o caixa da empresa para registrar recebimentos e pagamentos." /></x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($accounts as $account)
                <a href="{{ route('accounts.show', $account) }}" @class(['card block p-5 transition hover:border-brand-300 hover:shadow-sm dark:hover:border-brand-700', 'opacity-60' => ! $account->is_active])>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">{{ $account->name }}</p>
                            <p class="text-xs text-slate-500">{{ $account->type->label() }}{{ $account->bankDescription() ? ' · '.$account->bankDescription() : '' }}</p>
                        </div>
                        @unless ($account->is_active)<x-ui.badge>Inativa</x-ui.badge>@endunless
                    </div>
                    <p @class(['mt-4 text-2xl font-bold tabular-nums', 'text-red-600' => $account->current_balance_cents < 0, 'text-slate-900 dark:text-white' => $account->current_balance_cents >= 0])>{{ money($account->current_balance_cents) }}</p>
                    <p class="text-xs text-slate-500">Saldo inicial {{ money($account->opening_balance_cents) }} em {{ br_date($account->opening_date) }}</p>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts::app>
