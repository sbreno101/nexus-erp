@use('App\Enums\ProposalStatus')
<x-layouts::app :title="'Proposta '.$proposal->number">
    @php
        $canChange = auth()->user()->can('changeStatus', $proposal);
        $status = $proposal->status;
    @endphp

    <x-ui.page-header :title="$proposal->number.' — '.$proposal->title" :back="route('proposals.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
                <span>Cliente: <a href="{{ route('customers.show', $proposal->customer) }}" class="link">{{ $proposal->customer->display_name }}</a></span>
                <span>· Emitida em {{ br_date($proposal->issue_date) }} · válida até
                    <span @class(['font-semibold text-red-600' => $status === ProposalStatus::Sent && $proposal->isPastValidity()])>{{ br_date($proposal->valid_until) }}</span>
                </span>
            </div>
        </x-slot:meta>

        <x-ui.button variant="secondary" icon="printer" :href="route('proposals.pdf', $proposal)">PDF</x-ui.button>
        @can('update', $proposal)
            <x-ui.button variant="secondary" icon="pencil" :href="route('proposals.edit', $proposal)">Editar</x-ui.button>
        @endcan

        @if ($canChange)
            @if ($status->canTransitionTo(ProposalStatus::Sent))
                <x-ui.confirm-action :action="route('proposals.status', $proposal)" method="PATCH" title="Enviar proposta"
                    message="A proposta deixará de ser editável e passará a aguardar a decisão do cliente." confirm="Marcar como enviada" variant="primary" icon="paper-airplane">
                    <x-slot:fields><input type="hidden" name="status" value="sent"></x-slot:fields>
                    Enviar
                </x-ui.confirm-action>
            @endif
            @if ($status->canTransitionTo(ProposalStatus::Approved))
                <x-ui.confirm-action :action="route('proposals.status', $proposal)" method="PATCH" title="Registrar aprovação"
                    message="Registre a aprovação somente após o aceite formal do cliente. A oportunidade vinculada será marcada como ganha." confirm="Aprovar proposta" variant="success" icon="check-circle">
                    <x-slot:fields><input type="hidden" name="status" value="approved"></x-slot:fields>
                    Aprovar
                </x-ui.confirm-action>
            @endif
            @if ($status->canTransitionTo(ProposalStatus::Rejected))
                <x-ui.confirm-action :action="route('proposals.status', $proposal)" method="PATCH" title="Registrar recusa"
                    message="Informe o motivo da recusa — ele alimenta os relatórios comerciais." confirm="Recusar" variant="danger" icon="x-mark" reason reason-label="Motivo da recusa">
                    <x-slot:fields><input type="hidden" name="status" value="rejected"></x-slot:fields>
                    Recusar
                </x-ui.confirm-action>
            @endif
            @if ($status->canTransitionTo(ProposalStatus::Draft))
                <x-ui.confirm-action :action="route('proposals.status', $proposal)" method="PATCH" title="Voltar para rascunho"
                    message="A proposta poderá ser editada novamente (por exemplo, para revisar valores ou a validade)." confirm="Voltar para rascunho" variant="warning" icon="arrow-uturn-left">
                    <x-slot:fields><input type="hidden" name="status" value="draft"></x-slot:fields>
                    Revisar
                </x-ui.confirm-action>
            @endif
            @if ($status->canTransitionTo(ProposalStatus::Cancelled))
                <x-ui.confirm-action :action="route('proposals.status', $proposal)" method="PATCH" title="Cancelar proposta"
                    message="Propostas canceladas não podem ser reabertas." confirm="Cancelar proposta" icon="no-symbol" reason :reason-required="false" reason-label="Motivo (opcional)">
                    <x-slot:fields><input type="hidden" name="status" value="cancelled"></x-slot:fields>
                    Cancelar
                </x-ui.confirm-action>
            @endif
        @endif

        @if ($proposal->contract)
            <x-ui.button variant="success" icon="document-check" :href="route('contracts.show', $proposal->contract)">Ver contrato {{ $proposal->contract->number }}</x-ui.button>
        @elseif ($status === ProposalStatus::Approved && auth()->user()->can('convert', $proposal))
            <x-ui.button variant="success" icon="document-check" :href="route('proposals.convert', $proposal)">Gerar contrato</x-ui.button>
        @endif
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Itens" :padding="false">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr><th>Descrição</th><th class="text-right">Qtd.</th><th class="text-right">Preço unit.</th><th class="text-right">Desconto</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($proposal->items as $item)
                                <tr>
                                    <td class="!whitespace-normal">{{ $item->description }} @if ($item->catalogItem)<span class="block text-xs text-slate-500">{{ $item->catalogItem->code }}</span>@endif</td>
                                    <td class="text-right tabular-nums">{{ $item->formattedQuantity() }} {{ $item->unit }}</td>
                                    <td class="text-right tabular-nums">{{ money($item->unit_price_cents) }}</td>
                                    <td class="text-right tabular-nums">{{ $item->discount_cents ? money($item->discount_cents) : '—' }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ money($item->total_cents) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 text-sm dark:border-slate-700">
                            <tr><td colspan="4" class="px-4 py-2 text-right text-slate-500">Subtotal</td><td class="px-4 py-2 text-right tabular-nums">{{ money($proposal->subtotal_cents) }}</td></tr>
                            @if ($proposal->discount_cents)
                                <tr><td colspan="4" class="px-4 py-2 text-right text-slate-500">Desconto geral</td><td class="px-4 py-2 text-right tabular-nums">- {{ money($proposal->discount_cents) }}</td></tr>
                            @endif
                            <tr><td colspan="4" class="px-4 py-3 text-right font-semibold">Total</td><td class="px-4 py-3 text-right text-lg font-bold tabular-nums">{{ money($proposal->total_cents) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </x-ui.card>

            @if ($proposal->payment_terms || $proposal->notes)
                <x-ui.card title="Condições e observações">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div class="dl-item"><dt>Condições comerciais</dt><dd class="whitespace-pre-line">{{ $proposal->payment_terms ?? '—' }}</dd></div>
                        <div class="dl-item"><dt>Observações</dt><dd class="whitespace-pre-line">{{ $proposal->notes ?? '—' }}</dd></div>
                    </dl>
                </x-ui.card>
            @endif

            <x-attachments :model="$proposal" :attachments="$proposal->attachments" />
        </div>

        <div class="space-y-6">
            <x-ui.card title="Resumo">
                <dl class="space-y-3">
                    <div class="dl-item"><dt>Responsável</dt><dd>{{ $proposal->owner?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Oportunidade</dt><dd>@if ($proposal->opportunity)<a href="{{ route('opportunities.show', $proposal->opportunity) }}" class="link">{{ $proposal->opportunity->title }}</a>@else — @endif</dd></div>
                    <div class="dl-item"><dt>Enviada em</dt><dd>{{ br_date($proposal->sent_at, true) }}</dd></div>
                    <div class="dl-item"><dt>Aprovada em</dt><dd>{{ br_date($proposal->approved_at, true) }} @if ($proposal->approver) por {{ $proposal->approver->name }} @endif</dd></div>
                    @if ($proposal->rejection_reason)
                        <div class="dl-item"><dt>Motivo da recusa</dt><dd>{{ $proposal->rejection_reason }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-status-history :changes="$proposal->statusChanges" :enum="ProposalStatus::class" />
        </div>
    </div>

    @can('delete', $proposal)
        <div class="mt-8 flex justify-end">
            <x-ui.confirm-action :action="route('proposals.destroy', $proposal)" method="DELETE" title="Excluir rascunho" message="Somente rascunhos podem ser excluídos." confirm="Excluir" icon="trash" size="sm">Excluir rascunho</x-ui.confirm-action>
        </div>
    @endcan
</x-layouts::app>
