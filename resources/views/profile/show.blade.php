<x-layouts::app title="Meu perfil">
    <x-ui.page-header title="Meu perfil" subtitle="Dados pessoais, senha, sessões e acesso à API." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Dados pessoais: rota do Fortify (PUT /user/profile-information) --}}
            <x-ui.card title="Dados pessoais">
                <form method="POST" action="{{ route('user-profile-information.update') }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    @php($profileErrors = $errors->getBag('updateProfileInformation'))
                    <div>
                        <label class="form-label" for="profile-name">Nome</label>
                        <input id="profile-name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required maxlength="255">
                        @if ($profileErrors->has('name')) <p class="form-error">{{ $profileErrors->first('name') }}</p> @endif
                    </div>
                    <div>
                        <label class="form-label" for="profile-email">E-mail</label>
                        <input id="profile-email" type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        @if ($profileErrors->has('email')) <p class="form-error">{{ $profileErrors->first('email') }}</p> @endif
                        <p class="form-help">Ao alterar o e-mail, será necessário confirmá-lo novamente.</p>
                    </div>
                    <div>
                        <label class="form-label" for="profile-phone">Telefone</label>
                        <input id="profile-phone" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" maxlength="20">
                    </div>
                    <div>
                        <label class="form-label" for="profile-job">Cargo</label>
                        <input id="profile-job" name="job_title" class="form-control" value="{{ old('job_title', $user->job_title) }}" maxlength="255">
                    </div>
                    <div>
                        <label class="form-label" for="profile-theme">Tema preferido</label>
                        <select id="profile-theme" name="theme" class="form-control">
                            @foreach (\App\Enums\ThemePreference::options() as $value => $label)
                                <option value="{{ $value }}" @selected(old('theme', $user->theme?->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end justify-end sm:col-span-1">
                        <x-ui.button icon="check">Salvar dados</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Senha: rota do Fortify (PUT /user/password) --}}
            <x-ui.card title="Alterar senha">
                @php($passwordErrors = $errors->getBag('updatePassword'))
                <form method="POST" action="{{ route('user-password.update') }}" class="grid gap-4 sm:grid-cols-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="form-label" for="current_password">Senha atual</label>
                        <input id="current_password" type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                        @if ($passwordErrors->has('current_password')) <p class="form-error">{{ $passwordErrors->first('current_password') }}</p> @endif
                    </div>
                    <div>
                        <label class="form-label" for="password">Nova senha</label>
                        <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" required>
                        @if ($passwordErrors->has('password')) <p class="form-error">{{ $passwordErrors->first('password') }}</p> @endif
                    </div>
                    <div>
                        <label class="form-label" for="password_confirmation">Confirmação</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div class="flex justify-end sm:col-span-3">
                        <x-ui.button icon="key">Alterar senha</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card title="Sessões ativas" description="Dispositivos conectados à sua conta.">
                <ul class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($sessions as $session)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <div>
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $session->agent }}</p>
                                <p class="text-xs text-slate-500">{{ $session->ip_address }} · ativo {{ $session->last_active->diffForHumans() }}</p>
                            </div>
                            @if ($session->is_current)<x-ui.badge color="green">Esta sessão</x-ui.badge>@endif
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('profile.sessions.destroy') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('DELETE')
                    <x-form.input name="password" type="password" label="Confirme sua senha" class="min-w-56 flex-1" autocomplete="current-password" required />
                    <x-ui.button variant="secondary" icon="logout">Encerrar outras sessões</x-ui.button>
                </form>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Empresas e perfis">
                <ul class="space-y-3">
                    @if ($user->isSuperAdmin())
                        <li><x-ui.badge color="purple">Superadministrador</x-ui.badge> <span class="text-xs text-slate-500">acesso a todas as empresas</span></li>
                    @endif
                    @foreach ($memberships as $membership)
                        <li class="text-sm">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ $membership->company->display_name }}</p>
                            <p class="text-xs text-slate-500">{{ $membership->role->name }} @unless ($membership->is_active) · <span class="text-red-600">acesso desativado</span> @endunless</p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <x-ui.card title="Tokens de API" description="Para integrar outros sistemas à API /api/v1.">
                @if (session('plain_token'))
                    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-500/10" x-data="{ copied: false }">
                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">Copie o token agora — ele não será exibido novamente:</p>
                        <code class="mt-2 block break-all rounded bg-white p-2 text-xs dark:bg-slate-900">{{ session('plain_token') }}</code>
                        <button type="button" class="mt-2 text-xs link" x-on:click="navigator.clipboard.writeText(@js(session('plain_token'))); copied = true" x-text="copied ? 'Copiado!' : 'Copiar'"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.tokens.store') }}" class="space-y-3">
                    @csrf
                    <x-form.input name="token_name" label="Nome do token" placeholder="Ex.: Integração BI" required maxlength="100" />
                    <x-form.select name="expires_in_days" label="Validade" :options="['30' => '30 dias', '90' => '90 dias', '365' => '1 ano']" placeholder="Sem expiração" />
                    <x-ui.button size="sm" icon="key" class="w-full">Gerar token</x-ui.button>
                </form>

                <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($tokens as $token)
                        <li class="flex items-center justify-between py-2">
                            <div>
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $token->name }}</p>
                                <p class="text-xs text-slate-500">
                                    Criado {{ br_date($token->created_at) }} ·
                                    {{ $token->last_used_at ? 'usado '.$token->last_used_at->diffForHumans() : 'nunca usado' }}
                                    @if ($token->expires_at) · expira {{ br_date($token->expires_at) }} @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('profile.tokens.destroy', $token->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Revogar</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-slate-500">Nenhum token criado.</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts::app>
