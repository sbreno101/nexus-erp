<x-layouts::app title="Nova empresa">
    <x-ui.page-header title="Nova empresa" subtitle="A empresa será criada com os perfis de acesso e cadastros padrão." :back="route('admin.companies.index')" />

    <div class="mb-6 flex gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-500/10 dark:text-blue-300" role="status">
        <x-icon name="information-circle" class="size-5 shrink-0" />
        <p>Depois de cadastrar, selecione a nova empresa no seletor de empresas (topo da tela) e cadastre o primeiro usuário com o perfil <strong>Administrador da empresa</strong>.</p>
    </div>

    @include('admin.companies.form', [
        'action' => route('admin.companies.store'),
        'method' => 'POST',
    ])
</x-layouts::app>
