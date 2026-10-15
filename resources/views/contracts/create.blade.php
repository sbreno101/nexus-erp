<x-layouts::app title="Novo contrato">
    <x-ui.page-header title="Novo contrato" subtitle="Dica: contratos também podem ser gerados a partir de uma proposta aprovada." :back="route('contracts.index')" />
    @include('contracts.form', ['action' => route('contracts.store'), 'method' => 'POST', 'cancelUrl' => route('contracts.index')])
</x-layouts::app>
