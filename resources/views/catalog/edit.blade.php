<x-layouts::app :title="'Editar '.$catalogItem->name">
    <x-ui.page-header title="Editar item do catálogo" :subtitle="$catalogItem->code.' — '.$catalogItem->name" :back="route('catalog.show', $catalogItem)" />

    @include('catalog.form', [
        'catalogItem' => $catalogItem,
        'categories' => $categories,
        'action' => route('catalog.update', $catalogItem),
        'method' => 'PUT',
        'cancelUrl' => route('catalog.show', $catalogItem),
    ])
</x-layouts::app>
