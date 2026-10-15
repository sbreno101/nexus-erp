<x-layouts::app :title="$lead->name">
    <x-ui.page-header :title="$lead->name" :subtitle="$lead->company_name" :back="route('leads.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$lead->status->color()">{{ $lead->status->label() }}</x-ui.badge>
                @if ($lead->source) <span>Origem: {{ $lead->source->name }}</span> @endif
                @if ($lead->customer)
                    <span>· Convertido em <a href="{{ route('customers.show', $lead->customer) }}" class="link">{{ $lead->customer->display_name }}</a> em {{ br_date($lead->converted_at) }}</span>
                @endif
            </div>
        </x-slot:meta>

        @can('create', \App\Models\Opportunity::class)
            <x-ui.button variant="secondary" icon="plus" :href="route('opportunities.create', ['lead_id' => $lead->id])">Nova oportunidade</x-ui.button>
        @endcan
        @can('convert', $lead)
            <x-ui.button variant="success" icon="check-circle" :href="route('leads.convert', $lead)">Converter em cliente</x-ui.button>
        @endcan
        @can('update', $lead)
            <x-ui.button icon="pencil" :href="route('leads.edit', $lead)">Editar</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Informações">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div class="dl-item"><dt>E-mail</dt><dd>{{ $lead->email ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Telefone</dt><dd>{{ $lead->phone ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Documento</dt><dd>{{ $lead->document ? document_format($lead->document) : '—' }}</dd></div>
                    <div class="dl-item"><dt>Responsável</dt><dd>{{ $lead->owner?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Valor estimado</dt><dd>{{ money($lead->estimated_value_cents) }}</dd></div>
                    <div class="dl-item"><dt>Próximo contato</dt><dd>{{ br_date($lead->next_contact_at) }}</dd></div>
                    @if ($lead->notes)
                        <div class="dl-item sm:col-span-3"><dt>Observações</dt><dd class="whitespace-pre-line">{{ $lead->notes }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-crm.interactions :model="$lead" :interactions="$lead->interactions" />
        </div>

        <div class="space-y-6">
            <x-crm.tasks :model="$lead" :tasks="$lead->tasks" :users="$users" />

            <x-ui.card title="Oportunidades" :padding="false">
                @include('customers.partials.simple-list', ['items' => $lead->opportunities, 'route' => 'opportunities.show', 'label' => fn ($o) => $o->title, 'meta' => fn ($o) => $o->stage->name.' · '.money($o->estimated_value_cents), 'badge' => fn ($o) => $o->status])
            </x-ui.card>

            <x-audit-history :logs="$auditLogs" />
        </div>
    </div>

    @can('delete', $lead)
        <div class="mt-8 flex justify-end">
            <x-ui.confirm-action :action="route('leads.destroy', $lead)" method="DELETE" title="Excluir lead" message="O lead será removido da listagem." confirm="Excluir" icon="trash" size="sm">Excluir lead</x-ui.confirm-action>
        </div>
    @endcan
</x-layouts::app>
