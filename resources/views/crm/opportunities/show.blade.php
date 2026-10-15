<x-layouts::app :title="$opportunity->title">
    <x-ui.page-header :title="$opportunity->title" :back="route('opportunities.pipeline')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$opportunity->status->color()">{{ $opportunity->status->label() }}</x-ui.badge>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full" style="background: {{ $opportunity->stage->color }}"></span>{{ $opportunity->stage->name }}</span>
                <span>· {{ $opportunity->probability }}% de probabilidade</span>
            </div>
        </x-slot:meta>

        @if ($opportunity->customer && auth()->user()->can('create', \App\Models\Proposal::class))
            <x-ui.button variant="secondary" icon="document-text" :href="route('proposals.create', ['customer_id' => $opportunity->customer_id, 'opportunity_id' => $opportunity->id])">Criar proposta</x-ui.button>
        @endif
        @can('update', $opportunity)
            <x-ui.button icon="pencil" :href="route('opportunities.edit', $opportunity)">Editar</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Valor estimado" :value="money($opportunity->estimated_value_cents)" icon="banknotes" />
        <x-ui.stat label="Valor ponderado" :value="money($opportunity->weightedValueCents())" icon="chart-bar" color="purple" hint="Valor × probabilidade" />
        <x-ui.stat label="Previsão de fechamento" :value="br_date($opportunity->expected_close_date)" icon="calendar" color="blue" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Detalhes">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="dl-item"><dt>Cliente</dt><dd>
                        @if ($opportunity->customer)<a href="{{ route('customers.show', $opportunity->customer) }}" class="link">{{ $opportunity->customer->display_name }}</a>@else — @endif
                    </dd></div>
                    <div class="dl-item"><dt>Lead</dt><dd>
                        @if ($opportunity->lead)<a href="{{ route('leads.show', $opportunity->lead) }}" class="link">{{ $opportunity->lead->name }}</a>@else — @endif
                    </dd></div>
                    <div class="dl-item"><dt>Responsável</dt><dd>{{ $opportunity->owner?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Encerrada em</dt><dd>{{ br_date($opportunity->closed_at, true) }}</dd></div>
                    @if ($opportunity->lost_reason)
                        <div class="dl-item sm:col-span-2"><dt>Motivo da perda</dt><dd>{{ $opportunity->lost_reason }}</dd></div>
                    @endif
                    @if ($opportunity->description)
                        <div class="dl-item sm:col-span-2"><dt>Descrição</dt><dd class="whitespace-pre-line">{{ $opportunity->description }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card title="Propostas vinculadas" :padding="false">
                @include('customers.partials.simple-list', ['items' => $opportunity->proposals, 'route' => 'proposals.show', 'label' => fn ($p) => $p->number.' — '.$p->title, 'meta' => fn ($p) => money($p->total_cents), 'badge' => fn ($p) => $p->status])
            </x-ui.card>

            <x-crm.interactions :model="$opportunity" :interactions="$opportunity->interactions" />
        </div>

        <div class="space-y-6">
            <x-crm.tasks :model="$opportunity" :tasks="$opportunity->tasks" :users="$users" />
            <x-status-history :changes="$opportunity->statusChanges" :enum="\App\Enums\OpportunityStatus::class" />
        </div>
    </div>

    @can('delete', $opportunity)
        <div class="mt-8 flex justify-end">
            <x-ui.confirm-action :action="route('opportunities.destroy', $opportunity)" method="DELETE" title="Excluir oportunidade" message="A oportunidade será removida do funil." confirm="Excluir" icon="trash" size="sm">Excluir oportunidade</x-ui.confirm-action>
        </div>
    @endcan
</x-layouts::app>
