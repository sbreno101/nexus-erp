<div>
    <x-ui.page-header title="Dashboard" :subtitle="'Indicadores de '.$periodLabel">
        <div class="flex flex-wrap items-center gap-2">
            @if ($companies->count() > 1)
                <select wire:model.live="scope" class="form-control w-auto" aria-label="Empresa">
                    <option value="current">Empresa ativa</option>
                    <option value="all">Todas as minhas empresas</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->display_name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="date" wire:model.live="from" class="form-control w-auto" aria-label="Data inicial">
            <input type="date" wire:model.live="to" class="form-control w-auto" aria-label="Data final">
            <div class="flex rounded-lg border border-slate-200 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-800">
                @foreach (['month' => 'Mês', 'quarter' => 'Trimestre', 'semester' => '6 meses', 'year' => 'Ano'] as $period => $label)
                    <button type="button" wire:click="setPeriod('{{ $period }}')" class="rounded-md px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </x-ui.page-header>

    <div class="relative">
        <div wire:loading.delay class="absolute inset-0 z-10 rounded-xl bg-white/60 backdrop-blur-[1px] dark:bg-slate-950/60">
            <div class="flex justify-center pt-24">
                <span class="rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow dark:bg-slate-800 dark:text-slate-300">Atualizando indicadores…</span>
            </div>
        </div>

        {{-- Indicadores principais --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat label="Receita recebida" :value="money($kpis['revenue_received_cents'])" icon="arrow-trending-up" color="green" hint="Recebimentos confirmados no período" />
            <x-ui.stat label="Despesas pagas" :value="money($kpis['expenses_paid_cents'])" icon="arrow-trending-down" color="red" hint="Pagamentos confirmados no período" />
            <x-ui.stat label="Saldo financeiro" :value="money($kpis['balance_cents'])" icon="building-library" color="blue" hint="Soma das contas financeiras ativas" />
            <x-ui.stat label="Receita recorrente (MRR)" :value="money($kpis['monthly_recurring_cents'])" icon="arrow-path" color="purple" hint="Equivalente mensal dos contratos ativos" />

            <x-ui.stat label="Clientes ativos" :value="number_format($kpis['active_customers'], 0, ',', '.')" icon="users" :hint="$kpis['new_customers'].' novos no período'" :href="route('customers.index', ['status' => 'active'])" />
            <x-ui.stat label="Contratos ativos" :value="number_format($kpis['active_contracts'], 0, ',', '.')" icon="document-check" color="green" :href="route('contracts.index', ['status' => 'active'])" />
            <x-ui.stat label="Propostas em aberto" :value="$kpis['open_proposals']" icon="document-text" color="yellow" :hint="money($kpis['open_proposals_cents']).' em negociação'" :href="route('proposals.index')" />
            <x-ui.stat label="Taxa de conversão" :value="$kpis['conversion_rate'] === null ? '—' : number_format($kpis['conversion_rate'], 1, ',', '.').'%'" icon="funnel" color="purple" hint="Aprovadas ÷ decididas no período" />

            <x-ui.stat label="Contas a receber vencidas" :value="money($kpis['overdue_receivables_cents'])" icon="exclamation-triangle" color="red" :hint="$kpis['overdue_receivables_count'].' títulos vencidos'" :href="route('receivables.index', ['situacao' => 'overdue'])" />
            <x-ui.stat label="A receber (30 dias)" :value="money($kpis['upcoming_receivables_cents'])" icon="calendar" color="blue" :href="route('receivables.index', ['situacao' => 'upcoming'])" />
            <x-ui.stat label="A pagar (30 dias)" :value="money($kpis['upcoming_payables_cents'])" icon="calendar" color="yellow" :hint="'Vencidas: '.money($kpis['overdue_payables_cents'])" :href="route('payables.index', ['situacao' => 'upcoming'])" />
            <x-ui.stat label="Chamados em aberto" :value="$kpis['open_tickets']" icon="lifebuoy" color="blue" :hint="$kpis['pending_service_orders'].' OS pendentes ('.$kpis['overdue_service_orders'].' atrasadas)'" :href="route('tickets.index')" />
        </div>

        {{-- Gráficos --}}
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Receitas x despesas" description="Valores efetivamente recebidos e pagos por mês">
                <x-chart :config="[
                    'type' => 'bar',
                    'money' => true,
                    'data' => [
                        'labels' => $revenueChart['labels'],
                        'datasets' => [
                            ['label' => 'Receitas', 'data' => $revenueChart['income'], 'backgroundColor' => '#10b981', 'borderRadius' => 4],
                            ['label' => 'Despesas', 'data' => $revenueChart['expense'], 'backgroundColor' => '#ef4444', 'borderRadius' => 4],
                        ],
                    ],
                ]" />
            </x-ui.card>

            <x-ui.card title="Fluxo de caixa" description="Entradas, saídas e saldo acumulado (realizado)">
                <x-chart :config="[
                    'type' => 'line',
                    'money' => true,
                    'data' => [
                        'labels' => $cashFlowChart['labels'],
                        'datasets' => [
                            ['label' => 'Saldo acumulado', 'data' => $cashFlowChart['balance'], 'borderColor' => '#6366f1', 'backgroundColor' => 'rgba(99,102,241,0.12)', 'fill' => true, 'tension' => 0.3],
                            ['label' => 'Entradas', 'data' => $cashFlowChart['inflow'], 'borderColor' => '#10b981', 'tension' => 0.3],
                            ['label' => 'Saídas', 'data' => $cashFlowChart['outflow'], 'borderColor' => '#ef4444', 'tension' => 0.3],
                        ],
                    ],
                ]" />
            </x-ui.card>

            <x-ui.card title="Propostas por status" description="Propostas emitidas no período">
                <x-chart height="h-64" :config="[
                    'type' => 'doughnut',
                    'data' => [
                        'labels' => $proposalsChart['labels'],
                        'datasets' => [['data' => $proposalsChart['values'], 'backgroundColor' => $proposalsChart['colors']]],
                    ],
                ]" />
            </x-ui.card>

            <x-ui.card title="Oportunidades por etapa" :description="'Funil em aberto: '.money($kpis['open_pipeline_cents'])">
                <x-chart height="h-64" :config="[
                    'type' => 'bar',
                    'money' => true,
                    'data' => [
                        'labels' => $stagesChart['labels'],
                        'datasets' => [['label' => 'Valor estimado', 'data' => $stagesChart['values'], 'backgroundColor' => $stagesChart['colors'], 'borderRadius' => 4]],
                    ],
                ]" />
            </x-ui.card>

            <x-ui.card title="Novos clientes por mês" class="lg:col-span-2">
                <x-chart height="h-56" :config="[
                    'type' => 'bar',
                    'data' => [
                        'labels' => $customersChart['labels'],
                        'datasets' => [['label' => 'Clientes cadastrados', 'data' => $customersChart['values'], 'backgroundColor' => '#6366f1', 'borderRadius' => 4]],
                    ],
                ]" />
            </x-ui.card>
        </div>

        {{-- Acompanhamento operacional (somente na visão de uma empresa) --}}
        @if ($myTasks->isNotEmpty() || $urgentOrders->isNotEmpty() || $expiringContracts->isNotEmpty())
            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <x-ui.card title="Minhas tarefas" :padding="false">
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($myTasks as $task)
                            <li class="px-5 py-3">
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $task->title }}</p>
                                <p @class(['text-xs', 'font-semibold text-red-600' => $task->isOverdue(), 'text-slate-500' => ! $task->isOverdue()])>{{ $task->due_at ? 'Retorno '.br_date($task->due_at, true) : 'Sem data' }}</p>
                            </li>
                        @empty
                            <li><x-ui.empty-state icon="check-circle" title="Nenhuma tarefa pendente" /></li>
                        @endforelse
                    </ul>
                </x-ui.card>

                <x-ui.card title="Ordens de serviço prioritárias" :padding="false">
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($urgentOrders as $order)
                            <li class="flex items-center justify-between gap-2 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('service-orders.show', $order) }}" class="link block truncate text-sm">{{ $order->number }} — {{ $order->title }}</a>
                                    <p @class(['text-xs', 'font-semibold text-red-600' => $order->isOverdue(), 'text-slate-500' => ! $order->isOverdue()])>{{ $order->customer->display_name }} · prazo {{ br_date($order->due_date) }}</p>
                                </div>
                                <x-ui.badge :color="$order->priority->color()">{{ $order->priority->label() }}</x-ui.badge>
                            </li>
                        @empty
                            <li><x-ui.empty-state icon="wrench" title="Nenhuma OS pendente" /></li>
                        @endforelse
                    </ul>
                </x-ui.card>

                <x-ui.card title="Contratos a vencer (60 dias)" :padding="false">
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($expiringContracts as $contract)
                            <li class="px-5 py-3">
                                <a href="{{ route('contracts.show', $contract) }}" class="link block truncate text-sm">{{ $contract->number }} — {{ $contract->customer->display_name }}</a>
                                <p class="text-xs text-slate-500">Término {{ br_date($contract->end_date) }} · {{ $contract->auto_renew ? 'renovação automática' : 'sem renovação automática' }}</p>
                            </li>
                        @empty
                            <li><x-ui.empty-state icon="document-check" title="Nenhum contrato a vencer" /></li>
                        @endforelse
                    </ul>
                </x-ui.card>
            </div>
        @endif
    </div>
</div>
