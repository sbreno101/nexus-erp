<x-layouts::app :title="'Editar '.$account->name">
    <x-ui.page-header title="Editar conta financeira" :subtitle="$account->name" :back="route('accounts.show', $account)" />
    @include('finance.accounts.form', ['action' => route('accounts.update', $account), 'method' => 'PUT', 'cancelUrl' => route('accounts.show', $account)])
</x-layouts::app>
