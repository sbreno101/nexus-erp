<x-layouts::app title="Novo chamado">
    <x-ui.page-header title="Novo chamado" subtitle="O número é gerado automaticamente ao salvar." :back="route('tickets.index')" />

    @include('tickets.form', [
        'action' => route('tickets.store'),
        'method' => 'POST',
        'cancelUrl' => route('tickets.index'),
    ])
</x-layouts::app>
