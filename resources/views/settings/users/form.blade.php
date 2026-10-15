{{--
    Campos compartilhados do cadastro/edição de usuário.
    Variáveis: $user (User), $roles, $units, $membership (CompanyMembership|null), $isEdit (bool)
--}}
<x-ui.card title="Dados do usuário">
    <div class="grid gap-4 md:grid-cols-2">
        <x-form.input name="name" label="Nome completo" :value="$user->name" required maxlength="255" autocomplete="off" />

        @if ($isEdit)
            <div>
                <label for="field-email" class="form-label">E-mail</label>
                <input id="field-email" type="email" value="{{ $user->email }}" class="form-control bg-slate-50 dark:bg-slate-800/60" disabled>
                <p class="form-help">O e-mail identifica a conta em todas as empresas e só pode ser alterado pelo próprio usuário.</p>
            </div>
        @else
            <x-form.input name="email" type="email" label="E-mail" :value="$user->email" required maxlength="255" autocomplete="off"
                help="Se a pessoa já tiver conta em outra empresa, ela apenas será vinculada a esta." />
        @endif

        <x-form.input name="phone" label="Telefone" :value="$user->phone" maxlength="20" placeholder="(11) 98888-7777" />
        <x-form.input name="job_title" label="Cargo" :value="$user->job_title" maxlength="255" />
    </div>
</x-ui.card>

<x-ui.card title="Acesso à empresa" description="O perfil define o que o usuário pode ver e fazer nesta empresa.">
    <div class="grid gap-4 md:grid-cols-2">
        <x-form.select name="role_id" label="Perfil de acesso" :options="$roles" :value="$membership?->role_id" placeholder="Selecione..." required />
        <x-form.select name="company_unit_id" label="Unidade" :options="$units" :value="$membership?->company_unit_id" placeholder="Sem unidade definida" />
    </div>

    @can('viewAny', \App\Models\Role::class)
        <p class="mt-3 text-xs text-slate-500">
            As permissões de cada perfil são configuradas em <a href="{{ route('roles.index') }}" class="link">Perfis de acesso</a>.
        </p>
    @endcan
</x-ui.card>
