<x-layouts::app title="Novo perfil de acesso">
    <x-ui.page-header title="Novo perfil de acesso" subtitle="Crie um perfil e escolha as permissões dele." :back="route('roles.index')" />

    @include('settings.roles.form', [
        'action' => route('roles.store'),
        'method' => 'POST',
        'readOnly' => false,
    ])
</x-layouts::app>
