<x-layouts::app title="Nova unidade">
    <x-ui.page-header title="Nova unidade" subtitle="Cadastre uma filial ou escritório da empresa." :back="route('units.index')" />

    @include('settings.units.form', [
        'action' => route('units.store'),
        'method' => 'POST',
    ])
</x-layouts::app>
