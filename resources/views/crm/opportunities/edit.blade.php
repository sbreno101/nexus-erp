<x-layouts::app :title="'Editar '.$opportunity->title">
    <x-ui.page-header title="Editar oportunidade" :subtitle="$opportunity->title" :back="route('opportunities.show', $opportunity)" />
    @include('crm.opportunities.form', ['action' => route('opportunities.update', $opportunity), 'method' => 'PUT', 'cancelUrl' => route('opportunities.show', $opportunity)])
</x-layouts::app>
