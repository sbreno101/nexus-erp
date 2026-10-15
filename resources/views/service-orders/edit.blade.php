<x-layouts::app :title="'Editar OS '.$serviceOrder->number">
    <x-ui.page-header :title="'Editar OS '.$serviceOrder->number" :subtitle="$serviceOrder->title" :back="route('service-orders.show', $serviceOrder)" />

    @include('service-orders.form', [
        'action' => route('service-orders.update', $serviceOrder),
        'method' => 'PUT',
        'cancelUrl' => route('service-orders.show', $serviceOrder),
    ])
</x-layouts::app>
