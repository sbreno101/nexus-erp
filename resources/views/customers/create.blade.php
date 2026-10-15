<x-layouts::app title="Novo cliente">
    <x-ui.page-header title="Novo cliente" :back="route('customers.index')" />

    @include('parties.form', [
        'party' => $customer,
        'action' => route('customers.store'),
        'method' => 'POST',
        'statusOptions' => \App\Enums\CustomerStatus::options(),
        'users' => $users,
        'cancelUrl' => route('customers.index'),
    ])
</x-layouts::app>
