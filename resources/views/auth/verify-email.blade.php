<x-layouts::guest title="Verifique seu e-mail">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Verifique seu e-mail</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
        Enviamos um link de confirmação para <strong>{{ auth()->user()->email }}</strong>.
        Clique no link para liberar o acesso ao sistema.
    </p>

    <div class="mt-8 flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button class="w-full">Reenviar e-mail de verificação</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button variant="secondary" class="w-full">Sair</x-ui.button>
        </form>
    </div>
</x-layouts::guest>
