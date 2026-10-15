<x-layouts::app title="Novo usuário">
    <x-ui.page-header title="Novo usuário" subtitle="Dê acesso a uma pessoa da equipe." :back="route('users.index')" />

    <form method="POST" action="{{ route('users.store') }}" class="space-y-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
        @csrf

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @include('settings.users.form', ['membership' => null, 'isEdit' => false])
            </div>

            <div class="space-y-6">
                <x-ui.card title="Convite">
                    <div class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <p>Você não define a senha do usuário. Ele recebe um e-mail com um link seguro para criar a própria senha.</p>
                        <x-form.checkbox name="send_invitation" label="Enviar convite por e-mail agora" :checked="true"
                            help="Desmarque para cadastrar sem enviar; o usuário poderá usar a opção Esqueci minha senha depois." />
                    </div>
                </x-ui.card>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('users.index')">Cancelar</x-ui.button>
            <x-ui.button icon="check" x-bind:disabled="submitting">Cadastrar usuário</x-ui.button>
        </div>
    </form>
</x-layouts::app>
