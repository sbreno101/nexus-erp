<x-layouts::app title="Novo item do catálogo">
    <x-ui.page-header title="Novo item do catálogo" :back="route('catalog.index')" />

    @include('catalog.form', [
        'catalogItem' => $catalogItem,
        'categories' => $categories,
        'action' => route('catalog.store'),
        'method' => 'POST',
        'cancelUrl' => route('catalog.index'),
    ])
</x-layouts::app>
