<x-layouts::app :title="'Editar '.$customer->display_name">
    <x-ui.page-header :title="'Editar cliente'" :subtitle="$customer->name" :back="route('customers.show', $customer)" />

    @include('parties.form', [
        'party' => $customer,
        'action' => route('customers.update', $customer),
        'method' => 'PUT',
        'statusOptions' => \App\Enums\CustomerStatus::options(),
        'users' => $users,
        'cancelUrl' => route('customers.show', $customer),
    ])
</x-layouts::app>
