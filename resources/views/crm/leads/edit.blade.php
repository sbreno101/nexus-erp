<x-layouts::app :title="'Editar '.$lead->name">
    <x-ui.page-header title="Editar lead" :subtitle="$lead->name" :back="route('leads.show', $lead)" />
    @include('crm.leads.form', ['action' => route('leads.update', $lead), 'method' => 'PUT', 'cancelUrl' => route('leads.show', $lead)])
</x-layouts::app>
