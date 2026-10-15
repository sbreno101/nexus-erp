<x-layouts::guest title="Definir nova senha">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Definir nova senha</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Escolha uma senha com pelo menos 8 caracteres, contendo letras e números.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input name="email" type="email" label="E-mail" :value="$request->email" autocomplete="username" required />
        <x-form.input name="password" type="password" label="Nova senha" autocomplete="new-password" required />
        <x-form.input name="password_confirmation" type="password" label="Confirme a nova senha" autocomplete="new-password" required />

        <x-ui.button class="w-full" size="lg">Salvar senha</x-ui.button>
    </form>
</x-layouts::guest>
