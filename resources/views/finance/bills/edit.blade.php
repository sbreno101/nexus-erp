@php($isReceivable = $kind === 'receivable')
<x-layouts::app title="Editar título">
    <x-ui.page-header title="Editar título" :subtitle="$bill->description" :back="route($isReceivable ? 'receivables.show' : 'payables.show', $bill)" />
    @include('finance.bills.form', [
        'action' => route($isReceivable ? 'receivables.update' : 'payables.update', $bill),
        'method' => 'PUT',
        'cancelUrl' => route($isReceivable ? 'receivables.show' : 'payables.show', $bill),
    ])
</x-layouts::app>
