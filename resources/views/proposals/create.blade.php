<x-layouts::app title="Nova proposta">
    <x-ui.page-header title="Nova proposta" subtitle="A numeração é gerada automaticamente ao salvar." :back="route('proposals.index')" />
    @include('proposals.form', ['action' => route('proposals.store'), 'method' => 'POST', 'cancelUrl' => route('proposals.index')])
</x-layouts::app>
