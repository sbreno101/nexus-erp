<x-layouts::app :title="'Editar '.$unit->name">
    <x-ui.page-header title="Editar unidade" :subtitle="$unit->name.' ('.$unit->code.')'" :back="route('units.index')">
        @can('delete', $unit)
            <x-ui.confirm-action :action="route('units.destroy', $unit)" method="DELETE" title="Excluir unidade"
                :message="'Excluir a unidade '.$unit->name.'? Unidades com usuários ou contratos vinculados não podem ser excluídas; nesse caso, apenas inative-a.'"
                confirm="Excluir unidade" variant="danger" icon="trash">
                Excluir
            </x-ui.confirm-action>
        @endcan
    </x-ui.page-header>

    @include('settings.units.form', [
        'action' => route('units.update', $unit),
        'method' => 'PUT',
    ])
</x-layouts::app>
