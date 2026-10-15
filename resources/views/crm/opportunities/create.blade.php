<x-layouts::app title="Nova oportunidade">
    <x-ui.page-header title="Nova oportunidade" :back="route('opportunities.pipeline')" />
    @include('crm.opportunities.form', ['action' => route('opportunities.store'), 'method' => 'POST', 'cancelUrl' => route('opportunities.pipeline')])
</x-layouts::app>
