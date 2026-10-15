<x-layouts::guest title="Confirmar senha">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Confirme sua senha</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Esta é uma área sensível. Por segurança, confirme sua senha para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="password" type="password" label="Senha" autocomplete="current-password" autofocus required />
        <x-ui.button class="w-full" size="lg">Confirmar</x-ui.button>
    </form>
</x-layouts::guest>
