<x-layouts::app title="Editar despesa recorrente">
    <x-ui.page-header title="Editar despesa recorrente" :subtitle="$expense->description" :back="route('recurring-expenses.index')" />
    @include('finance.recurring.form', ['action' => route('recurring-expenses.update', $expense), 'method' => 'PUT'])
</x-layouts::app>
