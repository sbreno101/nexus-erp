<div>
    <x-ui.page-header title="Usuários" subtitle="Pessoas com acesso à empresa, seus perfis e unidades.">
        @can('viewAny', \App\Models\Role::class)
            <x-ui.button variant="secondary" icon="key" :href="route('roles.index')">Perfis de acesso</x-ui.button>
        @endcan
        @can('create', \App\Models\User::class)
            <x-ui.button icon="plus" :href="route('users.create')">Novo usuário</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />

        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar por nome ou e-mail..." />
            <select wire:model.live="role" class="form-control w-auto" aria-label="Filtrar por perfil">
                <option value="">Todos os perfis</option>
                @foreach ($roleOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="form-control w-auto" aria-label="Filtrar por situação">
                <option value="">Todas as situações</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @if ($search || $role || $status)
                <button type="button" wire:click="clearFilters" class="text-sm link">Limpar filtros</button>
            @endif
        </div>

        @if ($users->isEmpty())
            <x-ui.empty-state icon="users" title="Nenhum usuário encontrado" description="Ajuste os filtros ou cadastre um novo usuário.">
                @can('create', \App\Models\User::class)
                    <x-ui.button icon="plus" size="sm" :href="route('users.create')">Cadastrar usuário</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="name" :$sortField :$sortDirection>Usuário</x-table.sort-header>
                            <th>Perfil</th>
                            <th>Unidade</th>
                            <x-table.sort-header field="last_login_at" :$sortField :$sortDirection>Último login</x-table.sort-header>
                            <th>Situação</th>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($users as $user)
                            @php($membership = $user->memberships->first())
                            <tr wire:key="user-{{ $user->id }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ $user->initials() }}</span>
                                        <div class="min-w-0">
                                            @can('update', $user)
                                                <a href="{{ route('users.edit', $user) }}" class="link">{{ $user->name }}</a>
                                            @else
                                                <span class="font-medium text-slate-900 dark:text-white">{{ $user->name }}</span>
                                            @endcan
                                            @if ($user->isSuperAdmin())
                                                <x-ui.badge color="purple">Superadmin</x-ui.badge>
                                            @endif
                                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ $membership?->role?->name ?? '—' }}
                                    @if ($user->job_title)
                                        <p class="text-xs text-slate-500">{{ $user->job_title }}</p>
                                    @endif
                                </td>
                                <td>{{ $membership?->unit?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $user->last_login_at ? br_date($user->last_login_at, true) : 'Nunca acessou' }}</td>
                                <td>
                                    <div class="flex flex-col items-start gap-1">
                                        @if ($membership?->is_active)
                                            <x-ui.badge color="green">Acesso ativo</x-ui.badge>
                                        @else
                                            <x-ui.badge color="gray">Acesso desativado</x-ui.badge>
                                        @endif
                                        @unless ($user->is_active)
                                            <x-ui.badge color="red">Conta bloqueada</x-ui.badge>
                                        @endunless
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        @can('update', $user)
                                            <a href="{{ route('users.edit', $user) }}" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                        @endcan
                                        @can('toggleStatus', $user)
                                            @if ($membership?->is_active)
                                                <button type="button" wire:click="toggleAccess({{ $user->id }})" wire:confirm="Desativar o acesso de {{ $user->name }} a esta empresa? As sessões abertas serão encerradas."
                                                    class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Desativar acesso"><x-icon name="no-symbol" class="size-4" /></button>
                                            @else
                                                <button type="button" wire:click="toggleAccess({{ $user->id }})" wire:confirm="Reativar o acesso de {{ $user->name }} a esta empresa?"
                                                    class="rounded p-1.5 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10" title="Ativar acesso"><x-icon name="check-circle" class="size-4" /></button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $users->links() }}</div>
        @endif
    </div>
</div>
