<x-layouts::app title="Transferência entre contas">
    <x-ui.page-header title="Transferência entre contas" subtitle="Movimenta valores entre contas da mesma empresa (não altera o saldo consolidado)." :back="route('accounts.index')" />

    <div class="grid gap-6 lg:grid-cols-5">
        <form method="POST" action="{{ route('transfers.store') }}" class="lg:col-span-2" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <x-ui.card title="Nova transferência">
                <div class="space-y-4">
                    <x-form.select name="from_account_id" label="Conta de origem" :options="$accounts" placeholder="Selecione" required />
                    <x-form.select name="to_account_id" label="Conta de destino" :options="$accounts" placeholder="Selecione" required />
                    <x-form.money name="amount" label="Valor" required help="A origem precisa ter saldo suficiente." />
                    <x-form.input name="transferred_on" type="date" label="Data" :value="today()->toDateString()" required />
                    <x-form.input name="description" label="Descrição" maxlength="255" placeholder="Ex.: Reserva para impostos" />
                    <x-ui.button class="w-full" icon="arrows-right-left" x-bind:disabled="submitting">Transferir</x-ui.button>
                </div>
            </x-ui.card>
        </form>

        <x-ui.card title="Transferências recentes" class="lg:col-span-3" :padding="false">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Data</th><th>De → Para</th><th class="text-right">Valor</th><th>Situação</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($recent as $transfer)
                            <tr>
                                <td>{{ br_date($transfer->transferred_on) }}</td>
                                <td class="!whitespace-normal">{{ $transfer->fromAccount->name }} → {{ $transfer->toAccount->name }}<p class="text-xs text-slate-500">{{ $transfer->description }}</p></td>
                                <td class="text-right tabular-nums">{{ money($transfer->amount_cents) }}</td>
                                <td><x-ui.badge :color="$transfer->status->color()">{{ $transfer->status->label() }}</x-ui.badge></td>
                                <td class="text-right">
                                    @can('reverse', $transfer)
                                        <x-ui.confirm-action :action="route('transfers.reverse', $transfer)" method="PATCH" title="Estornar transferência" message="Lançamentos inversos serão registrados nas duas contas." confirm="Estornar" icon="arrow-uturn-left" size="sm" reason reason-label="Motivo">Estornar</x-ui.confirm-action>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty-state icon="arrows-right-left" title="Nenhuma transferência" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
</x-layouts::app>
