@use('App\Enums\ContractStatus')
<x-layouts::app :title="'Contrato '.$contract->number">
    @php
        $status = $contract->status;
        $canManage = auth()->user()->can('manage', $contract);
    @endphp

    <x-ui.page-header :title="$contract->number.' — '.$contract->title" :back="route('contracts.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
                <span>Cliente: <a href="{{ route('customers.show', $contract->customer) }}" class="link">{{ $contract->customer->display_name }}</a></span>
                @if ($contract->proposal)
                    <span>· Origem: <a href="{{ route('proposals.show', $contract->proposal) }}" class="link">proposta {{ $contract->proposal->number }}</a></span>
                @endif
            </div>
        </x-slot:meta>

        @can('update', $contract)
            <x-ui.button variant="secondary" icon="pencil" :href="route('contracts.edit', $contract)">Editar</x-ui.button>
        @endcan

        @if ($canManage)
            @if ($status->canTransitionTo(ContractStatus::Active))
                <x-ui.confirm-action :action="route('contracts.status', $contract)" method="PATCH" :title="$status === ContractStatus::Suspended ? 'Reativar contrato' : 'Ativar contrato'"
                    :message="$status === ContractStatus::Suspended ? 'A cobrança será retomada a partir do próximo vencimento. O período suspenso não é cobrado.' : 'O contrato passará a gerar cobranças automaticamente conforme a periodicidade.'"
                    :confirm="$status === ContractStatus::Suspended ? 'Reativar' : 'Ativar'" variant="success" icon="play">
                    <x-slot:fields><input type="hidden" name="status" value="active"></x-slot:fields>
                    {{ $status === ContractStatus::Suspended ? 'Reativar' : 'Ativar' }}
                </x-ui.confirm-action>
            @endif
            @if ($status === ContractStatus::Active)
                <x-ui.confirm-action :action="route('contracts.charges', $contract)" title="Gerar cobranças agora"
                    message="Gera as cobranças com vencimento dentro da janela de antecedência configurada. Cobranças já existentes não são duplicadas." confirm="Gerar cobranças" variant="primary" icon="banknotes">
                    Gerar cobranças
                </x-ui.confirm-action>
            @endif
            @if ($status->canTransitionTo(ContractStatus::Suspended))
                <x-ui.confirm-action :action="route('contracts.status', $contract)" method="PATCH" title="Suspender contrato"
                    message="Novas cobranças deixam de ser geradas até a reativação. Cobranças existentes permanecem." confirm="Suspender" variant="warning" icon="pause" reason :reason-required="false" reason-label="Motivo (opcional)">
                    <x-slot:fields><input type="hidden" name="status" value="suspended"></x-slot:fields>
                    Suspender
                </x-ui.confirm-action>
            @endif
            @if ($contract->end_date && in_array($status, [ContractStatus::Active, ContractStatus::Suspended], true))
                <x-ui.confirm-action :action="route('contracts.renew', $contract)" title="Renovar vigência"
                    :message="'O término será estendido por '.$contract->renewal_months.' meses (a partir de '.br_date($contract->end_date).').'" confirm="Renovar" variant="primary" icon="arrow-path">
                    Renovar
                </x-ui.confirm-action>
            @endif
            @if ($status->canTransitionTo(ContractStatus::Finished))
                <x-ui.confirm-action :action="route('contracts.status', $contract)" method="PATCH" title="Encerrar contrato"
                    message="Encerramento natural da vigência. Nenhuma nova cobrança será gerada." confirm="Encerrar" variant="secondary" icon="check-circle">
                    <x-slot:fields><input type="hidden" name="status" value="finished"></x-slot:fields>
                    Encerrar
                </x-ui.confirm-action>
            @endif
            @if ($status->canTransitionTo(ContractStatus::Cancelled))
                <x-ui.confirm-action :action="route('contracts.status', $contract)" method="PATCH" title="Cancelar contrato"
                    message="Cobranças futuras em aberto serão canceladas automaticamente. Cobranças vencidas ou pagas permanecem. Esta ação não pode ser desfeita." confirm="Cancelar contrato" icon="no-symbol" reason reason-label="Motivo do cancelamento">
                    <x-slot:fields><input type="hidden" name="status" value="cancelled"></x-slot:fields>
                    Cancelar
                </x-ui.confirm-action>
            @endif
        @endif
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Valor por período" :value="money($contract->amount_cents)" :hint="$contract->billing_frequency->label().' · vence dia '.$contract->billing_day" icon="banknotes" />
        <x-ui.stat label="Próxima cobrança" :value="br_date($contract->next_billing_date)" icon="calendar" color="blue" :hint="$status->generatesCharges() ? 'Gerada automaticamente' : 'Contrato não gera cobranças'" />
        <x-ui.stat label="Total faturado" :value="money((int) $billing->billed)" icon="document-text" color="purple" />
        <x-ui.stat label="Total recebido" :value="money((int) $billing->paid)" icon="check-circle" color="green" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Serviços contratados" :padding="false">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Descrição</th><th class="text-right">Qtd.</th><th class="text-right">Preço unit.</th><th class="text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($contract->items as $item)
                                <tr>
                                    <td class="!whitespace-normal">{{ $item->description }}</td>
                                    <td class="text-right tabular-nums">{{ $item->formattedQuantity() }} {{ $item->unit }}</td>
                                    <td class="text-right tabular-nums">{{ money($item->unit_price_cents) }}</td>
                                    <td class="text-right tabular-nums">{{ money($item->total_cents) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Cobranças geradas" description="Contas a receber vinculadas a este contrato (últimas 24)." :padding="false">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Competência</th><th>Vencimento</th><th class="text-right">Valor</th><th class="text-right">Pago</th><th>Situação</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($contract->receivables as $receivable)
                                <tr>
                                    <td><a href="{{ route('receivables.show', $receivable) }}" class="link">{{ $receivable->competence->format('m/Y') }}</a></td>
                                    <td>{{ br_date($receivable->due_date) }}</td>
                                    <td class="text-right tabular-nums">{{ money($receivable->amount_cents) }}</td>
                                    <td class="text-right tabular-nums">{{ money($receivable->paid_cents) }}</td>
                                    <td><x-ui.badge :color="$receivable->statusColor()">{{ $receivable->statusLabel() }}</x-ui.badge></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-ui.empty-state icon="banknotes" title="Nenhuma cobrança gerada ainda" description="Cobranças são geradas para contratos ativos, com a antecedência definida nas configurações." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <div class="grid gap-6 md:grid-cols-2">
                <x-ui.card title="Ordens de serviço" :padding="false">
                    <x-slot:actions>
                        @can('create', \App\Models\ServiceOrder::class)
                            <x-ui.button size="sm" variant="secondary" icon="plus" :href="route('service-orders.create', ['customer_id' => $contract->customer_id, 'contract_id' => $contract->id])">Nova</x-ui.button>
                        @endcan
                    </x-slot:actions>
                    @include('customers.partials.simple-list', ['items' => $contract->serviceOrders, 'route' => 'service-orders.show', 'label' => fn ($s) => $s->number.' — '.$s->title, 'meta' => fn ($s) => 'Prazo '.br_date($s->due_date), 'badge' => fn ($s) => $s->status])
                </x-ui.card>
                <x-ui.card title="Chamados" :padding="false">
                    <x-slot:actions>
                        @can('create', \App\Models\Ticket::class)
                            <x-ui.button size="sm" variant="secondary" icon="plus" :href="route('tickets.create', ['customer_id' => $contract->customer_id, 'contract_id' => $contract->id])">Novo</x-ui.button>
                        @endcan
                    </x-slot:actions>
                    @include('customers.partials.simple-list', ['items' => $contract->tickets, 'route' => 'tickets.show', 'label' => fn ($t) => $t->number.' — '.$t->subject, 'meta' => fn ($t) => br_date($t->created_at), 'badge' => fn ($t) => $t->status])
                </x-ui.card>
            </div>

            <x-attachments :model="$contract" :attachments="$contract->attachments" />
        </div>

        <div class="space-y-6">
            <x-ui.card title="Vigência">
                <dl class="space-y-3">
                    <div class="dl-item"><dt>Início</dt><dd>{{ br_date($contract->start_date) }}</dd></div>
                    <div class="dl-item"><dt>Término</dt><dd>{{ $contract->end_date ? br_date($contract->end_date) : 'Prazo indeterminado' }}</dd></div>
                    <div class="dl-item"><dt>Renovação</dt><dd>{{ $contract->auto_renew ? 'Automática por '.$contract->renewal_months.' meses' : 'Manual' }}</dd></div>
                    <div class="dl-item"><dt>Assinado em</dt><dd>{{ br_date($contract->signed_at) }}</dd></div>
                    <div class="dl-item"><dt>Unidade</dt><dd>{{ $contract->unit?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Responsável</dt><dd>{{ $contract->owner?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Categoria financeira</dt><dd>{{ $contract->category?->name ?? '—' }}</dd></div>
                    @if ($contract->cancellation_reason)
                        <div class="dl-item"><dt>Motivo do cancelamento</dt><dd>{{ $contract->cancellation_reason }}</dd></div>
                    @endif
                    @if ($contract->description)
                        <div class="dl-item"><dt>Escopo</dt><dd class="whitespace-pre-line">{{ $contract->description }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-status-history :changes="$contract->statusChanges" :enum="ContractStatus::class" />
            <x-audit-history :logs="$auditLogs" />
        </div>
    </div>
</x-layouts::app>
