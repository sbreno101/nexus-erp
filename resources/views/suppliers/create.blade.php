<x-layouts::app title="Novo fornecedor">
    <x-ui.page-header title="Novo fornecedor" :back="route('suppliers.index')" />

    @include('parties.form', [
        'party' => $supplier,
        'action' => route('suppliers.store'),
        'method' => 'POST',
        'statusOptions' => \App\Enums\RecordStatus::options(),
        'cancelUrl' => route('suppliers.index'),
    ])
</x-layouts::app>
