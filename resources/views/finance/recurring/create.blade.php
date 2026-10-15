<x-layouts::app title="Nova despesa recorrente">
    <x-ui.page-header title="Nova despesa recorrente" :back="route('recurring-expenses.index')" />
    @include('finance.recurring.form', ['action' => route('recurring-expenses.store'), 'method' => 'POST'])
</x-layouts::app>
