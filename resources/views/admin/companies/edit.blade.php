<x-layouts::app :title="'Editar '.$company->display_name">
    <x-ui.page-header title="Editar empresa" :subtitle="$company->display_name" :back="route('admin.companies.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$company->status->color()">{{ $company->status->label() }}</x-ui.badge>
                <span>{{ trans_choice(':count usuário vinculado|:count usuários vinculados', $company->memberships_count) }}</span>
                <span>· Cadastrada em {{ br_date($company->created_at) }}</span>
            </div>
        </x-slot:meta>
    </x-ui.page-header>

    @include('admin.companies.form', [
        'action' => route('admin.companies.update', $company),
        'method' => 'PUT',
    ])
</x-layouts::app>
