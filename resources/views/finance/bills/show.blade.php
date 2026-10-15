{{-- Detalhe de conta a receber/pagar: resumo, baixa, histórico de pagamentos e estornos. --}}
@php
    $isReceivable = $kind === 'receivable';
    $routePrefix = $isReceivable ? 'receivables' : 'payables';
    $party = $isReceivable ? $bill->customer : $bill->supplier;
    $canSettle = $bill->status->acceptsPayment() && auth()->user()->can('settle', $bill);
@endphp
<x-layouts::app :title="$bill->description">
    <x-ui.page-header :title="$bill->description" :back="route($routePrefix.'.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$bill->statusColor()">{{ $bill->statusLabel() }}</x-ui.badge>
                <span>{{ $isReceivable ? 'Cliente' : 'Fornecedor' }}:
                    @if ($party)
                        <a href="{{ route($isReceivable ? 'customers.show' : 'suppliers.show', $party) }}" class="link">{{ $party->display_name }}</a>
                    @else — @endif
                </span>
                @if ($isReceivable && $bill->contract)
                    <span>· Contrato <a href="{{ route('contracts.show', $bill->contract) }}" class="link">{{ $bill->contract->number }}</a></span>
                @endif
                @if (! $isReceivable && $bill->recurringExpense)
                    <span>· Despesa recorrente</span>
                @endif
            </div>
        </x-slot:meta>

        @can('update', $bill)
            <x-ui.button variant="secondary" icon="pencil" :href="route($routePrefix.'.edit', $bill)">Editar</x-ui.button>
        @endcan
        @if ($canSettle)
            <x-ui.button :variant="$isReceivable ? 'success' : 'primary'" icon="banknotes" type="button" x-data x-on:click="$dispatch('open-modal', 'record-payment')">
                {{ $isReceivable ? 'Registrar recebimento' : 'Registrar pagamento' }}
            </x-ui.button>
        @endif
        @if ($bill->status !== \App\Enums\BillStatus::Cancelled && auth()->user()->can('cancel', $bill))
            <x-ui.confirm-action :action="route($routePrefix.'.cancel', $bill)" method="PATCH" title="Cancelar título"
                message="Títulos com pagamentos precisam ter os pagamentos estornados antes. O título não será excluído: fica registrado como cancelado."
                confirm="Cancelar título" icon="no-symbol" reason reason-label="Motivo do cancelamento">
                Cancelar
            </x-ui.confirm-action>
        @endif
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Valor" :value="money($bill->amount_cents)" icon="document-text" />
        <x-ui.stat :label="$isReceivable ? 'Recebido' : 'Pago'" :value="money($bill->paid_cents)" icon="check-circle" color="green" />
        <x-ui.stat label="Em aberto" :value="money($bill->outstandingCents())" icon="banknotes" :color="$bill->isOverdue() ? 'red' : 'blue'" />
        <x-ui.stat label="Vencimento" :value="br_date($bill->due_date)" icon="calendar" :color="$bill->isOverdue() ? 'red' : 'yellow'" :hint="$bill->isOverdue() ? 'Vencido há '.$bill->due_date->diffInDays(today()).' dia(s)' : null" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Histórico de pagamentos" description="Pagamentos nunca são apagados: correções são feitas por estorno." :padding="false">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Data</th><th>Conta</th><th>Forma</th><th class="text-right">Valor</th><th>Situação</th><th></th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($bill->payments as $payment)
                                <tr>
                                    <td>{{ br_date($payment->paid_on) }}<p class="text-xs text-slate-500">por {{ $payment->user?->name ?? 'Sistema' }}</p></td>
                                    <td>{{ $payment->account->name }}</td>
                                    <td>{{ $payment->method->label() }}</td>
                                    <td @class(['text-right tabular-nums', 'line-through text-slate-400' => $payment->isReversed()])>{{ money($payment->amount_cents) }}</td>
                                    <td>
                                        <x-ui.badge :color="$payment->status->color()">{{ $payment->status->label() }}</x-ui.badge>
                                        @if ($payment->isReversed())
                                            <p class="mt-1 max-w-48 whitespace-normal text-xs text-slate-500">{{ br_date($payment->reversed_at, true) }} · {{ $payment->reverser?->name }}: {{ $payment->reversal_reason }}</p>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @can('reverse', $payment)
                                            <x-ui.confirm-action :action="route('payments.reverse', $payment)" method="PATCH" title="Estornar pagamento"
                                                :message="'O valor de '.money($payment->amount_cents).' volta a ficar em aberto e um lançamento inverso é registrado no extrato da conta.'"
                                                confirm="Estornar" icon="arrow-uturn-left" size="sm" reason reason-label="Motivo do estorno">
                                                Estornar
                                            </x-ui.confirm-action>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ui.empty-state icon="banknotes" title="Nenhum pagamento registrado" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-attachments :model="$bill" :attachments="$bill->attachments" />
        </div>

        <div class="space-y-6">
            <x-ui.card title="Detalhes">
                <dl class="space-y-3">
                    <div class="dl-item"><dt>Competência</dt><dd>{{ $bill->competence->format('m/Y') }}</dd></div>
                    <div class="dl-item"><dt>Emissão</dt><dd>{{ br_date($bill->issue_date) }}</dd></div>
                    <div class="dl-item"><dt>Categoria</dt><dd>{{ $bill->category?->name ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Centro de custo</dt><dd>{{ $bill->costCenter?->label() ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Documento</dt><dd>{{ $bill->document_number ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>{{ $isReceivable ? 'Quitado em' : 'Pago em' }}</dt><dd>{{ br_date($bill->paid_at) }}</dd></div>
                    @if ($bill->cancelled_at)
                        <div class="dl-item"><dt>Cancelado</dt><dd>{{ br_date($bill->cancelled_at, true) }} por {{ $bill->canceller?->name ?? 'Sistema' }}<br><span class="text-slate-500">{{ $bill->cancellation_reason }}</span></dd></div>
                    @endif
                    @if ($bill->notes)
                        <div class="dl-item"><dt>Observações</dt><dd class="whitespace-pre-line">{{ $bill->notes }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-audit-history :logs="$auditLogs" />
        </div>
    </div>

    @if ($canSettle)
        <x-ui.modal name="record-payment" :title="$isReceivable ? 'Registrar recebimento' : 'Registrar pagamento'">
            <form method="POST" action="{{ route($routePrefix.'.payments.store', $bill) }}" class="space-y-4" x-data="{ submitting: false }" x-on:submit="submitting = true">
                @csrf
                {{-- Chave de idempotência: um duplo clique ou reenvio não duplica o pagamento. --}}
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                <x-form.select name="financial_account_id" :label="$isReceivable ? 'Conta de destino' : 'Conta de origem'" :options="$accounts" placeholder="Selecione a conta" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.money name="amount" label="Valor" :value="$bill->outstandingCents()" required help="Pode ser parcial." />
                    <x-form.input name="paid_on" type="date" label="Data" :value="today()->toDateString()" :max="today()->toDateString()" required />
                </div>
                <x-form.select name="method" label="Forma de pagamento" :options="\App\Enums\PaymentMethod::options()" value="pix" required />
                <x-form.input name="notes" label="Observação" maxlength="255" />
                <p class="text-xs text-slate-500">Registro manual: o Nexus ERP não processa pagamentos nem se comunica com bancos.</p>
                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'record-payment')">Cancelar</x-ui.button>
                    <x-ui.button icon="check" x-bind:disabled="submitting">Confirmar</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->hasAny(['amount', 'amount_cents', 'financial_account_id', 'paid_on', 'method']))
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'record-payment'))"></div>
        @endif
    @endif
</x-layouts::app>
