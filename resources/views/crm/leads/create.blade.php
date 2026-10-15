<x-layouts::app title="Novo lead">
    <x-ui.page-header title="Novo lead" :back="route('leads.index')" />
    @include('crm.leads.form', ['action' => route('leads.store'), 'method' => 'POST', 'cancelUrl' => route('leads.index')])
</x-layouts::app>
