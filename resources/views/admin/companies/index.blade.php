{{-- Variáveis: $companies (paginator), $search, $status, $statusOptions --}}
<x-layouts::app title="Empresas">
    <x-ui.page-header title="Empresas" subtitle="Empresas (clientes da plataforma) cadastradas no sistema.">
        <x-ui.button icon="plus" :href="route('admin.companies.create')">Nova empresa</x-ui.button>
    </x-ui.page-header>

    <div class="card">
        <form method="GET" action="{{ route('admin.companies.index') }}" class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <div class="relative w-full sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <x-icon name="magnifying-glass" class="size-4" />
                </span>
                <input type="search" name="busca" value="{{ $search }}" placeholder="Buscar por nome, e-mail ou CNPJ..." aria-label="Buscar empresas" class="form-control pl-9">
            </div>
            <select name="status" class="form-control w-auto" aria-label="Filtrar por status" onchange="this.form.submit()">
                <option value="">Todos os status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-ui.button variant="secondary" size="sm" icon="magnifying-glass">Filtrar</x-ui.button>
            @if ($search !== '' || $status !== '')
                <a href="{{ route('admin.companies.index') }}" class="text-sm link">Limpar filtros</a>
            @endif
        </form>

        @if ($companies->isEmpty())
            <x-ui.empty-state icon="building" title="Nenhuma empresa encontrada" description="Ajuste os filtros ou cadastre uma nova empresa.">
                <x-ui.button icon="plus" size="sm" :href="route('admin.companies.create')">Cadastrar empresa</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>CNPJ</th>
                            <th>Cidade/UF</th>
                            <th class="text-center">Usuários</th>
                            <th>Status</th>
                            <th>Cadastrada em</th>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($companies as $company)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.companies.edit', $company) }}" class="link">{{ $company->display_name }}</a>
                                    @if ($company->trade_name)
                                        <p class="text-xs text-slate-500">{{ $company->legal_name }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap tabular-nums">{{ document_format($company->cnpj) }}</td>
                                <td>{{ collect([$company->city, $company->state])->filter()->implode('/') ?: '—' }}</td>
                                <td class="text-center tabular-nums">{{ $company->memberships_count }}</td>
                                <td><x-ui.badge :color="$company->status->color()">{{ $company->status->label() }}</x-ui.badge></td>
                                <td class="whitespace-nowrap">{{ br_date($company->created_at) }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.companies.edit', $company) }}" class="inline-flex rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($companies->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $companies->links() }}</div>
            @endif
        @endif
    </div>
</x-layouts::app>
