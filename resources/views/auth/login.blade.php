<x-layouts::guest title="Entrar">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Acesse sua conta</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Informe seu e-mail e senha para continuar.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" x-data="{ submitting: false }" x-on:submit="submitting = true">
        @csrf

        <x-form.input name="email" type="email" label="E-mail" autocomplete="username" autofocus required />

        <div>
            <div class="flex items-center justify-between">
                <label for="field-password" class="form-label">Senha</label>
                <a href="{{ route('password.request') }}" class="mb-1 text-xs link">Esqueceu a senha?</a>
            </div>
            <x-form.input name="password" type="password" autocomplete="current-password" required />
        </div>

        <x-form.checkbox name="remember" label="Manter conectado neste dispositivo" />

        <x-ui.button class="w-full" size="lg" x-bind:disabled="submitting">
            <span x-show="!submitting">Entrar</span>
            <span x-show="submitting" x-cloak>Entrando…</span>
        </x-ui.button>
    </form>

    @if (app()->isLocal())
        <div class="mt-8 rounded-lg border border-dashed border-amber-300 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
            <strong>Ambiente de desenvolvimento:</strong> as credenciais de demonstração estão no arquivo README.md.
        </div>
    @endif
</x-layouts::guest>
