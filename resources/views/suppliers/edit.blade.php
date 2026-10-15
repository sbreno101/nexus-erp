<x-layouts::app :title="'Editar '.$supplier->display_name">
    <x-ui.page-header title="Editar fornecedor" :subtitle="$supplier->name" :back="route('suppliers.show', $supplier)" />

    @include('parties.form', [
        'party' => $supplier,
        'action' => route('suppliers.update', $supplier),
        'method' => 'PUT',
        'statusOptions' => \App\Enums\RecordStatus::options(),
        'cancelUrl' => route('suppliers.show', $supplier),
    ])
</x-layouts::app>
