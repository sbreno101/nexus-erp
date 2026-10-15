{{--
    Edição de usuário da empresa ativa.
    Variáveis: $user, $membership (vínculo com a empresa ativa), $otherMemberships (somente superadmin), $roles, $units
--}}
<x-layouts::app :title="'Editar '.$user->name">
    <x-ui.page-header title="Editar usuário" :subtitle="$user->name" :back="route('users.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                @if ($membership->is_active)
                    <x-ui.badge color="green">Acesso ativo</x-ui.badge>
                @else
                    <x-ui.badge color="gray">Acesso desativado</x-ui.badge>
                @endif
                @unless ($user->is_active)
                    <x-ui.badge color="red">Conta bloqueada</x-ui.badge>
                @endunless
                <span>{{ $user->email }}</span>
            </div>
        </x-slot:meta>

        @can('toggleStatus', $user)
            @if ($membership->is_active)
                <x-ui.confirm-action :action="route('users.toggle', $user)" method="PATCH" title="Desativar acesso"
                    message="O usuário perderá o acesso a esta empresa e as sessões abertas serão encerradas. Outras empresas em que ele trabalha não são afetadas."
                    confirm="Desativar acesso" variant="danger" icon="no-symbol">
                    <x-slot:fields>
                        <input type="hidden" name="active" value="0">
                        <input type="hidden" name="scope" value="company">
                    </x-slot:fields>
                    Desativar acesso
                </x-ui.confirm-action>
            @else
                <x-ui.confirm-action :action="route('users.toggle', $user)" method="PATCH" title="Ativar acesso"
                    message="O usuário voltará a acessar esta empresa com o perfil atual."
                    confirm="Ativar acesso" variant="success" icon="check-circle">
                    <x-slot:fields>
                        <input type="hidden" name="active" value="1">
                        <input type="hidden" name="scope" value="company">
                    </x-slot:fields>
                    Ativar acesso
                </x-ui.confirm-action>
            @endif
        @endcan
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6 lg:col-span-2" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            @method('PUT')

            @include('settings.users.form', ['isEdit' => true])

            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="route('users.index')">Cancelar</x-ui.button>
                <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
            </div>
        </form>

        <div class="space-y-6">
            <x-ui.card title="Vínculo com a empresa">
                <dl class="grid gap-4">
                    <div class="dl-item"><dt>Perfil</dt><dd>{{ $membership->role?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Unidade</dt><dd>{{ $membership->unit?->name ?? 'Sem unidade definida' }}</dd></div>
                    <div class="dl-item"><dt>Vinculado em</dt><dd>{{ br_date($membership->created_at) }}</dd></div>
                    <div class="dl-item"><dt>Último login</dt><dd>{{ $user->last_login_at ? br_date($user->last_login_at, true) : 'Nunca acessou' }}</dd></div>
                    <div class="dl-item"><dt>E-mail verificado</dt><dd>{{ $user->email_verified_at ? 'Sim, em '.br_date($user->email_verified_at) : 'Ainda não' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Senha e convite">
                <div class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <p>Administradores não definem nem visualizam senhas. Ao ser cadastrado, o usuário recebe um convite por e-mail com um link para criar a própria senha.</p>
                    <p>Se o link expirar ou a senha for esquecida, o próprio usuário pode usar a opção <strong>Esqueci minha senha</strong> na tela de login.</p>
                </div>
            </x-ui.card>

            @if (auth()->user()->isSuperAdmin())
                <x-ui.card title="Outras empresas" description="Visível apenas para o superadministrador.">
                    @forelse ($otherMemberships as $otherMembership)
                        <div class="mb-3 flex items-start justify-between gap-2 last:mb-0">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $otherMembership->company?->display_name ?? '—' }}</p>
                                <p class="text-xs text-slate-500">{{ $otherMembership->role?->name ?? 'Sem perfil' }}</p>
                            </div>
                            <x-ui.badge :color="$otherMembership->is_active ? 'green' : 'gray'">{{ $otherMembership->is_active ? 'Ativo' : 'Inativo' }}</x-ui.badge>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">O usuário não possui vínculo com outras empresas.</p>
                    @endforelse
                </x-ui.card>

                @unless ($user->is(auth()->user()))
                    <x-ui.card title="Conta global">
                        <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">
                            {{ $user->is_active ? 'Bloquear a conta impede o login em TODAS as empresas.' : 'A conta está bloqueada em todas as empresas'.($user->deactivated_at ? ' desde '.br_date($user->deactivated_at, true) : '').'.' }}
                        </p>
                        <x-ui.confirm-action :action="route('users.toggle', $user)" method="PATCH"
                            :title="$user->is_active ? 'Bloquear conta' : 'Reativar conta'"
                            :message="$user->is_active ? 'O usuário não conseguirá entrar em nenhuma empresa e todas as sessões e tokens serão revogados.' : 'O usuário voltará a conseguir entrar nas empresas em que possui vínculo ativo.'"
                            :confirm="$user->is_active ? 'Bloquear conta' : 'Reativar conta'"
                            :variant="$user->is_active ? 'danger' : 'success'" :icon="$user->is_active ? 'no-symbol' : 'check-circle'" size="sm">
                            <x-slot:fields>
                                <input type="hidden" name="active" value="{{ $user->is_active ? 0 : 1 }}">
                                <input type="hidden" name="scope" value="global">
                            </x-slot:fields>
                            {{ $user->is_active ? 'Bloquear conta' : 'Reativar conta' }}
                        </x-ui.confirm-action>
                    </x-ui.card>
                @endunless
            @endif
        </div>
    </div>
</x-layouts::app>
