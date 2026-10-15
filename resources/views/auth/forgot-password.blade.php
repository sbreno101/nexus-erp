<x-layouts::guest title="Recuperar senha">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Recuperar senha</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Informe o e-mail cadastrado. Se ele existir, enviaremos um link para você criar uma nova senha.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" type="email" label="E-mail" autocomplete="username" autofocus required />
        <x-ui.button class="w-full" size="lg">Enviar link de recuperação</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="link">Voltar para o login</a></p>
</x-layouts::guest>
