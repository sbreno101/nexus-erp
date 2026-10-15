<x-layouts::app :title="'Editar '.$proposal->number">
    <x-ui.page-header :title="'Editar proposta '.$proposal->number" :back="route('proposals.show', $proposal)" />
    @include('proposals.form', ['action' => route('proposals.update', $proposal), 'method' => 'PUT', 'cancelUrl' => route('proposals.show', $proposal)])
</x-layouts::app>
