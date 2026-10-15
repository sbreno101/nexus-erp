<x-layouts::app title="Nova ordem de serviço">
    <x-ui.page-header title="Nova ordem de serviço" subtitle="O número é gerado automaticamente ao salvar." :back="route('service-orders.index')" />

    @include('service-orders.form', [
        'action' => route('service-orders.store'),
        'method' => 'POST',
        'cancelUrl' => route('service-orders.index'),
    ])
</x-layouts::app>
