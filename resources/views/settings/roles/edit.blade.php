{{-- Variáveis: $role, $groups, $selected, $readOnly --}}
<x-layouts::app :title="($readOnly ? 'Perfil ' : 'Editar perfil ').$role->name">
    <x-ui.page-header :title="$readOnly ? 'Perfil de acesso' : 'Editar perfil de acesso'" :subtitle="$role->name" :back="route('roles.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                @if ($role->is_system)
                    <x-ui.badge color="indigo">Padrão do sistema</x-ui.badge>
                @endif
                <span>{{ trans_choice(':count usuário vinculado|:count usuários vinculados', $role->memberships_count) }}</span>
            </div>
        </x-slot:meta>

        @can('delete', $role)
            <x-ui.confirm-action :action="route('roles.destroy', $role)" method="DELETE" title="Excluir perfil"
                :message="'Excluir o perfil '.$role->name.'? Perfis com usuários vinculados não podem ser excluídos.'"
                confirm="Excluir perfil" variant="danger" icon="trash">
                Excluir
            </x-ui.confirm-action>
        @endcan
    </x-ui.page-header>

    @if ($role->isCompanyAdmin())
        <div class="mb-6 flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300" role="status">
            <x-icon name="information-circle" class="size-5 shrink-0" />
            <p>O perfil <strong>Administrador da empresa</strong> sempre possui todas as permissões e não pode ser alterado. Isso garante que a empresa nunca perca o acesso administrativo.</p>
        </div>
    @elseif ($readOnly)
        <div class="mb-6 flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300" role="status">
            <x-icon name="eye" class="size-5 shrink-0" />
            <p>Você está visualizando este perfil. Para alterá-lo é necessária a permissão "Gerenciar perfis e permissões".</p>
        </div>
    @endif

    @include('settings.roles.form', [
        'action' => route('roles.update', $role),
        'method' => 'PUT',
    ])
</x-layouts::app>
