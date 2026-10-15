<x-layouts::app title="Perfis de acesso">
    <x-ui.page-header title="Perfis de acesso" subtitle="Defina o que cada grupo de usuários pode ver e fazer na empresa.">
        @can('viewAny', \App\Models\User::class)
            <x-ui.button variant="secondary" icon="users" :href="route('users.index')">Usuários</x-ui.button>
        @endcan
        @can('create', \App\Models\Role::class)
            <x-ui.button icon="plus" :href="route('roles.create')">Novo perfil</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card">
        @if ($roles->isEmpty())
            <x-ui.empty-state icon="key" title="Nenhum perfil cadastrado" description="Crie perfis para organizar as permissões da equipe.">
                @can('create', \App\Models\Role::class)
                    <x-ui.button icon="plus" size="sm" :href="route('roles.create')">Criar perfil</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Perfil</th>
                            <th>Tipo</th>
                            <th class="text-center">Permissões</th>
                            <th class="text-center">Usuários</th>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($roles as $role)
                            <tr>
                                <td>
                                    <a href="{{ route('roles.edit', $role) }}" class="link">{{ $role->name }}</a>
                                    @if ($role->description)
                                        <p class="text-xs text-slate-500">{{ $role->description }}</p>
                                    @endif
                                </td>
                                <td>
                                    @if ($role->isCompanyAdmin())
                                        <x-ui.badge color="purple">Acesso total</x-ui.badge>
                                    @elseif ($role->is_system)
                                        <x-ui.badge color="indigo">Padrão do sistema</x-ui.badge>
                                    @else
                                        <x-ui.badge color="gray">Personalizado</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-center tabular-nums">{{ $role->permissions_count }} <span class="text-xs text-slate-400">/ {{ $totalPermissions }}</span></td>
                                <td class="text-center tabular-nums">{{ $role->memberships_count }}</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        @can('update', $role)
                                            <a href="{{ route('roles.edit', $role) }}" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                        @else
                                            <a href="{{ route('roles.edit', $role) }}" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Visualizar"><x-icon name="eye" class="size-4" /></a>
                                        @endcan
                                        @can('delete', $role)
                                            <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm(@js('Excluir o perfil '.$role->name.'?'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-400 dark:hover:bg-red-500/10"
                                                    title="{{ $role->memberships_count > 0 ? 'Perfil em uso: não pode ser excluído' : 'Excluir' }}"
                                                    @disabled($role->memberships_count > 0)>
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
        @endif
    </div>
</x-layouts::app>
