<x-layouts::app :title="$supplier->display_name">
    <x-ui.page-header :title="$supplier->display_name" :back="route('suppliers.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$supplier->status->color()">{{ $supplier->status->label() }}</x-ui.badge>
                <span>{{ $supplier->type->documentLabel() }} {{ $supplier->formatted_document }}</span>
            </div>
        </x-slot:meta>
        @can('create', \App\Models\Payable::class)
            <x-ui.button variant="secondary" icon="plus" :href="route('payables.create', ['supplier_id' => $supplier->id])">Nova conta a pagar</x-ui.button>
        @endcan
        @can('update', $supplier)
            <x-ui.button icon="pencil" :href="route('suppliers.edit', $supplier)">Editar</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="A pagar em aberto" :value="money($openCents)" icon="arrow-trending-down" color="red" />
        <x-ui.stat label="Total pago" :value="money($paidCents)" icon="check-circle" color="green" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Dados cadastrais">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="dl-item"><dt>Razão social</dt><dd>{{ $supplier->name }}</dd></div>
                    <div class="dl-item"><dt>E-mail</dt><dd>{{ $supplier->email ?? '—' }}</dd></div>
                    <div class="dl-item"><dt>Telefones</dt><dd>{{ collect([$supplier->phone, $supplier->mobile])->filter()->implode(' · ') ?: '—' }}</dd></div>
                    <div class="dl-item"><dt>Site</dt><dd>{{ $supplier->website ?? '—' }}</dd></div>
                    @if ($supplier->notes)
                        <div class="dl-item sm:col-span-2"><dt>Observações</dt><dd class="whitespace-pre-line">{{ $supplier->notes }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card title="Contas a pagar recentes" :padding="false">
                @include('customers.partials.simple-list', ['items' => $supplier->payables, 'route' => 'payables.show', 'label' => fn ($p) => $p->description, 'meta' => fn ($p) => 'Venc. '.br_date($p->due_date).' · '.money($p->amount_cents).($p->category ? ' · '.$p->category->name : ''), 'badge' => null, 'customBadge' => fn ($p) => [$p->statusColor(), $p->statusLabel()]])
            </x-ui.card>

            <x-attachments :model="$supplier" :attachments="$supplier->attachments" />
        </div>

        <div class="space-y-6">
            <x-ui.card title="Endereços">
                @forelse ($supplier->addresses as $address)
                    <p class="mb-2 text-sm text-slate-600 dark:text-slate-300"><span class="font-medium">{{ $address->label }}:</span> {{ $address->oneLine() }}</p>
                @empty
                    <p class="text-sm text-slate-500">Nenhum endereço cadastrado.</p>
                @endforelse
            </x-ui.card>
            <x-ui.card title="Contatos">
                @forelse ($supplier->contacts as $contact)
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $contact->name }}</p>
                    <p class="mb-2 text-xs text-slate-500">{{ collect([$contact->position, $contact->email, $contact->phone])->filter()->implode(' · ') }}</p>
                @empty
                    <p class="text-sm text-slate-500">Nenhum contato cadastrado.</p>
                @endforelse
            </x-ui.card>
            <x-ui.card title="Despesas recorrentes">
                @forelse ($supplier->recurringExpenses as $expense)
                    <p class="text-sm text-slate-700 dark:text-slate-200">{{ $expense->description }} — {{ money($expense->amount_cents) }} ({{ mb_strtolower($expense->frequency->label()) }})</p>
                @empty
                    <p class="text-sm text-slate-500">Nenhuma despesa recorrente.</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>

    @can('delete', $supplier)
        <div class="mt-8 flex justify-end">
            <x-ui.confirm-action :action="route('suppliers.destroy', $supplier)" method="DELETE" title="Excluir fornecedor"
                message="Fornecedores com contas a pagar em aberto não podem ser excluídos." confirm="Excluir" icon="trash" size="sm">Excluir fornecedor</x-ui.confirm-action>
        </div>
    @endcan
</x-layouts::app>
