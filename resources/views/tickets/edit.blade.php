<x-layouts::app :title="'Editar chamado '.$ticket->number">
    <x-ui.page-header :title="'Editar chamado '.$ticket->number" :subtitle="$ticket->subject" :back="route('tickets.show', $ticket)" />

    @include('tickets.form', [
        'action' => route('tickets.update', $ticket),
        'method' => 'PUT',
        'cancelUrl' => route('tickets.show', $ticket),
    ])
</x-layouts::app>
