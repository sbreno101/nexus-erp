{{--
    Menu lateral. Cada item só aparece se o usuário tiver a permissão
    correspondente (@can) — mas a proteção REAL está nas rotas e Policies:
    esconder um link não impede o acesso direto pela URL.
--}}
@php
    $sections = [
        null => [
            ['Dashboard', 'dashboard', 'home', 'dashboard.view', 'dashboard'],
        ],
        'Comercial' => [
            ['Clientes', 'customers.index', 'users', 'customers.view', 'customers.*'],
            ['Leads', 'leads.index', 'funnel', 'leads.view', 'leads.*'],
            ['Funil de vendas', 'opportunities.pipeline', 'view-columns', 'opportunities.view', 'opportunities.*'],
            ['Tarefas', 'tasks.index', 'clipboard-list', 'dashboard.view', 'tasks.*'],
            ['Catálogo', 'catalog.index', 'cube', 'catalog.view', 'catalog.*'],
            ['Propostas', 'proposals.index', 'document-text', 'proposals.view', 'proposals.*'],
            ['Contratos', 'contracts.index', 'document-check', 'contracts.view', 'contracts.*'],
        ],
        'Operações' => [
            ['Ordens de serviço', 'service-orders.index', 'wrench', 'service_orders.view', 'service-orders.*'],
            ['Chamados', 'tickets.index', 'lifebuoy', 'tickets.view', 'tickets.*'],
        ],
        'Financeiro' => [
            ['Contas a receber', 'receivables.index', 'arrow-trending-up', 'receivables.view', 'receivables.*'],
            ['Contas a pagar', 'payables.index', 'arrow-trending-down', 'payables.view', 'payables.*'],
            ['Fornecedores', 'suppliers.index', 'truck', 'suppliers.view', 'suppliers.*'],
            ['Contas financeiras', 'accounts.index', 'building-library', 'accounts.view', 'accounts.*|transfers.*'],
            ['Fluxo de caixa', 'cash-flow.index', 'banknotes', 'cash_flow.view', 'cash-flow.*'],
        ],
        'Gestão' => [
            ['Relatórios', 'reports.index', 'presentation-chart', null, 'reports.*'],
            ['Usuários', 'users.index', 'user', 'users.view', 'users.*'],
            ['Perfis de acesso', 'roles.index', 'key', 'roles.view', 'roles.*'],
            ['Unidades', 'units.index', 'building', 'units.view', 'units.*'],
            ['Auditoria', 'audit.index', 'shield-check', 'audit.view', 'audit.*'],
            ['Configurações', 'settings.company.edit', 'cog', 'settings.manage', 'settings.*'],
        ],
    ];

    $canSeeReports = auth()->user()->hasAnyPermission('reports.commercial', 'reports.financial', 'reports.operational');
@endphp

<nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-3 py-4" aria-label="Menu principal">
    @foreach ($sections as $section => $items)
        @php
            $visibleItems = array_filter($items, fn ($item) => $item[3] === null ? $canSeeReports : auth()->user()->can($item[3]));
        @endphp

        @if ($visibleItems !== [])
            <div>
                @if ($section)
                    <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $section }}</p>
                @endif
                <div class="space-y-0.5">
                    @foreach ($visibleItems as [$label, $route, $icon, $permission, $pattern])
                        <x-nav.link :href="route($route)" :icon="$icon" :active="request()->routeIs(...explode('|', $pattern))">
                            {{ $label }}
                        </x-nav.link>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    @if (auth()->user()->isSuperAdmin())
        <div>
            <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Plataforma</p>
            <x-nav.link :href="route('admin.companies.index')" icon="squares" :active="request()->routeIs('admin.companies.*')">
                Empresas
            </x-nav.link>
        </div>
    @endif
</nav>
