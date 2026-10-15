<x-layouts::app title="Nova conta financeira">
    <x-ui.page-header title="Nova conta financeira" :back="route('accounts.index')" />
    @include('finance.accounts.form', ['action' => route('accounts.store'), 'method' => 'POST', 'cancelUrl' => route('accounts.index')])
</x-layouts::app>
