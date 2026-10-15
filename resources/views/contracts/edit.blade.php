<x-layouts::app :title="'Editar '.$contract->number">
    <x-ui.page-header :title="'Editar contrato '.$contract->number" subtitle="As alterações ficam registradas no histórico do contrato." :back="route('contracts.show', $contract)" />
    @include('contracts.form', ['action' => route('contracts.update', $contract), 'method' => 'PUT', 'cancelUrl' => route('contracts.show', $contract)])
</x-layouts::app>
