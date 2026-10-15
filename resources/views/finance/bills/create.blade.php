@php($isReceivable = $kind === 'receivable')
<x-layouts::app :title="$isReceivable ? 'Nova conta a receber' : 'Nova conta a pagar'">
    <x-ui.page-header :title="$isReceivable ? 'Nova conta a receber' : 'Nova conta a pagar'" :back="route($isReceivable ? 'receivables.index' : 'payables.index')" />
    @include('finance.bills.form', [
        'action' => route($isReceivable ? 'receivables.store' : 'payables.store'),
        'method' => 'POST',
        'cancelUrl' => route($isReceivable ? 'receivables.index' : 'payables.index'),
    ])
</x-layouts::app>
