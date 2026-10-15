<x-layouts::app title="Unidades">
    <x-ui.page-header title="Unidades" subtitle="Filiais, escritórios e pontos de atendimento da empresa.">
        @can('create', \App\Models\CompanyUnit::class)
            <x-ui.button icon="plus" :href="route('units.create')">Nova unidade</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card">
        @if ($units->isEmpty())
            <x-ui.empty-state icon="building" title="Nenhuma unidade cadastrada" description="Cadastre as filiais para organizar usuários e contratos por local.">
                @can('create', \App\Models\CompanyUnit::class)
                    <x-ui.button icon="plus" size="sm" :href="route('units.create')">Cadastrar unidade</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Unidade</th>
                            <th>CNPJ</th>
                            <th>Cidade/UF</th>
                            <th>Contato</th>
                            <th class="text-center">Usuários</th>
                            <th class="text-center">Contratos</th>
                            <th>Situação</th>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($units as $unit)
                            <tr>
                                <td>
                                    @can('update', $unit)
                                        <a href="{{ route('units.edit', $unit) }}" class="link">{{ $unit->name }}</a>
                                    @else
                                        <span class="font-medium text-slate-900 dark:text-white">{{ $unit->name }}</span>
                                    @endcan
                                    <p class="font-mono text-xs text-slate-500">{{ $unit->code }}</p>
                                </td>
                                <td class="tabular-nums">{{ $unit->cnpj ? document_format($unit->cnpj) : '—' }}</td>
                                <td>{{ collect([$unit->city, $unit->state])->filter()->implode('/') ?: '—' }}</td>
                                <td>
                                    <p>{{ $unit->email ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ $unit->phone }}</p>
                                </td>
                                <td class="text-center tabular-nums">{{ $unit->memberships_count }}</td>
                                <td class="text-center tabular-nums">{{ $unit->contracts_count }}</td>
                                <td>
                                    <x-ui.badge :color="$unit->is_active ? 'green' : 'gray'">{{ $unit->is_active ? 'Ativa' : 'Inativa' }}</x-ui.badge>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        @can('update', $unit)
                                            <a href="{{ route('units.edit', $unit) }}" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                        @endcan
                                        @can('delete', $unit)
                                            @php($inUse = $unit->memberships_count > 0 || $unit->contracts_count > 0)
                                            <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm(@js('Excluir a unidade '.$unit->name.'?'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" @disabled($inUse)
                                                    title="{{ $inUse ? 'Unidade em uso: inative-a em vez de excluir' : 'Excluir' }}"
                                                    class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-400 dark:hover:bg-red-500/10">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($units->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $units->links() }}</div>
            @endif
        @endif
    </div>
</x-layouts::app>
