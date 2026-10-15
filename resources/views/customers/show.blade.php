<x-layouts::app :title="$customer->display_name">
    <x-ui.page-header :title="$customer->display_name" :back="route('customers.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$customer->status->color()">{{ $customer->status->label() }}</x-ui.badge>
                <span>{{ $customer->type->documentLabel() }} {{ $customer->formatted_document }}</span>
                @if ($customer->originLead)
                    <span>· Origem: lead {{ $customer->originLead->source?->name ? '('.$customer->originLead->source->name.')' : '' }}</span>
                @endif
            </div>
        </x-slot:meta>

        @can('create', \App\Models\Proposal::class)
            <x-ui.button variant="secondary" icon="document-text" :href="route('proposals.create', ['customer_id' => $customer->id])">Nova proposta</x-ui.button>
        @endcan
        @can('create', \App\Models\Ticket::class)
            <x-ui.button variant="secondary" icon="lifebuoy" :href="route('tickets.create', ['customer_id' => $customer->id])">Abrir chamado</x-ui.button>
        @endcan
        @can('update', $customer)
            <x-ui.button icon="pencil" :href="route('customers.edit', $customer)">Editar</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Em aberto" :value="money($financialSummary['open_cents'])" icon="banknotes" color="blue" />
        <x-ui.stat label="Vencido" :value="money($financialSummary['overdue_cents'])" icon="exclamation-triangle" color="red" />
        <x-ui.stat label="Total recebido" :value="money($financialSummary['paid_cents'])" icon="check-circle" color="green" />
    </div>

    <div x-data="{ tab: 'overview' }">
        <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800" role="tablist">
            @foreach (['overview' => 'Visão geral', 'relationship' => 'Relacionamento', 'commercial' => 'Comercial', 'finance' => 'Financeiro', 'support' => 'Atendimento', 'documents' => 'Documentos', 'history' => 'Histórico'] as $key => $label)
                <button type="button" role="tab" x-on:click="tab = '{{ $key }}'" x-bind:aria-selected="tab === '{{ $key }}'"
                    class="whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium transition"
                    x-bind:class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Visão geral --}}
        <div x-show="tab === 'overview'" class="grid gap-6 lg:grid-cols-3">
            <x-ui.card title="Dados cadastrais" class="lg:col-span-2">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="dl-item"><dt>Nome / Razão social</dt><dd>{{ $customer->name }}</dd></div>
                    <div class="dl-item"><dt>Nome fantasia</dt><dd>{{ $customer->trade_name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>E-mail</dt><dd>{{ $customer->email ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Telefones</dt><dd>{{ collect([$customer->phone, $customer->mobile])->filter()->implode(' · ') ?: '—' }}</dd></div>
                    <div class="dl-item"><dt>Inscrição estadual</dt><dd>{{ $customer->state_registration ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Site</dt><dd>{{ $customer->website ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Responsável comercial</dt><dd>{{ $customer->owner?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Cliente desde</dt><dd>{{ br_date($customer->created_at) }}</dd></div>
                    @if ($customer->notes)
                        <div class="dl-item sm:col-span-2"><dt>Observações</dt><dd class="whitespace-pre-line">{{ $customer->notes }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <div class="space-y-6">
                <x-ui.card title="Endereços">
                    @forelse ($customer->addresses as $address)
                        <div class="mb-3 last:mb-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $address->label }} @if ($address->is_primary)<x-ui.badge color="indigo">Principal</x-ui.badge>@endif</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $address->oneLine() }}</p>
                            @if ($address->zip_code)<p class="text-xs text-slate-500">CEP {{ substr($address->zip_code, 0, 5) }}-{{ substr($address->zip_code, 5) }}</p>@endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum endereço cadastrado.</p>
                    @endforelse
                </x-ui.card>

                <x-ui.card title="Contatos">
                    @forelse ($customer->contacts as $contact)
                        <div class="mb-3 last:mb-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $contact->name }} @if ($contact->is_primary)<x-ui.badge color="indigo">Principal</x-ui.badge>@endif</p>
                            <p class="text-xs text-slate-500">{{ collect([$contact->position, $contact->email, $contact->phone])->filter()->implode(' · ') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhum contato cadastrado.</p>
                    @endforelse
                </x-ui.card>
            </div>
        </div>

        {{-- Relacionamento --}}
        <div x-show="tab === 'relationship'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <x-crm.interactions :model="$customer" :interactions="$customer->interactions" />
            <x-crm.tasks :model="$customer" :tasks="$customer->tasks" :users="$users" />
        </div>

        {{-- Comercial --}}
        <div x-show="tab === 'commercial'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Propostas" :padding="false">
                @include('customers.partials.simple-list', ['items' => $customer->proposals, 'route' => 'proposals.show', 'label' => fn ($p) => $p->number.' — '.$p->title, 'meta' => fn ($p) => money($p->total_cents), 'badge' => fn ($p) => $p->status])
            </x-ui.card>
            <x-ui.card title="Contratos" :padding="false">
                @include('customers.partials.simple-list', ['items' => $customer->contracts, 'route' => 'contracts.show', 'label' => fn ($c) => $c->number.' — '.$c->title, 'meta' => fn ($c) => money($c->amount_cents).' / '.mb_strtolower($c->billing_frequency->label()), 'badge' => fn ($c) => $c->status])
            </x-ui.card>
            <x-ui.card title="Oportunidades" :padding="false" class="lg:col-span-2">
                @include('customers.partials.simple-list', ['items' => $customer->opportunities, 'route' => 'opportunities.show', 'label' => fn ($o) => $o->title.' ('.$o->stage->name.')', 'meta' => fn ($o) => money($o->estimated_value_cents), 'badge' => fn ($o) => $o->status])
            </x-ui.card>
        </div>

        {{-- Financeiro --}}
        <div x-show="tab === 'finance'" x-cloak>
            <x-ui.card title="Últimas contas a receber" :padding="false">
                @include('customers.partials.simple-list', ['items' => $customer->receivables, 'route' => 'receivables.show', 'label' => fn ($r) => $r->description, 'meta' => fn ($r) => 'Venc. '.br_date($r->due_date).' · '.money($r->amount_cents), 'badge' => null, 'customBadge' => fn ($r) => [$r->statusColor(), $r->statusLabel()]])
            </x-ui.card>
        </div>

        {{-- Atendimento --}}
        <div x-show="tab === 'support'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Chamados" :padding="false">
                @include('customers.partials.simple-list', ['items' => $customer->tickets, 'route' => 'tickets.show', 'label' => fn ($t) => $t->number.' — '.$t->subject, 'meta' => fn ($t) => br_date($t->created_at), 'badge' => fn ($t) => $t->status])
            </x-ui.card>
            <x-ui.card title="Ordens de serviço" :padding="false">
                @include('customers.partials.simple-list', ['items' => $customer->serviceOrders, 'route' => 'service-orders.show', 'label' => fn ($s) => $s->number.' — '.$s->title, 'meta' => fn ($s) => 'Prazo '.br_date($s->due_date), 'badge' => fn ($s) => $s->status])
            </x-ui.card>
        </div>

        {{-- Documentos --}}
        <div x-show="tab === 'documents'" x-cloak>
            <x-attachments :model="$customer" :attachments="$customer->attachments" />
        </div>

        {{-- Histórico --}}
        <div x-show="tab === 'history'" x-cloak>
            <x-audit-history :logs="$auditLogs" />
        </div>
    </div>

    @can('delete', $customer)
        <div class="mt-8 flex justify-end">
            <x-ui.confirm-action :action="route('customers.destroy', $customer)" method="DELETE" title="Excluir cliente"
                message="O cliente será removido das listagens. Clientes com contratos ativos ou cobranças em aberto não podem ser excluídos."
                confirm="Excluir cliente" icon="trash" size="sm">Excluir cliente</x-ui.confirm-action>
        </div>
    @endcan
</x-layouts::app>
