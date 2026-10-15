{{-- Variáveis: $account, $types, $action, $method, $cancelUrl --}}
<form method="POST" action="{{ $action }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Conta financeira">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="name" label="Nome" class="md:col-span-3" :value="$account->name" required maxlength="100" placeholder="Ex.: Itaú — Conta PJ" />
            <x-form.select name="type" label="Tipo" class="md:col-span-3" :options="$types" :value="$account->type" required />
            <x-form.input name="bank_name" label="Banco / instituição" class="md:col-span-3" :value="$account->bank_name" maxlength="100" />
            <x-form.input name="bank_code" label="Código do banco" class="md:col-span-1" :value="$account->bank_code" maxlength="5" />
            <x-form.input name="agency" label="Agência" class="md:col-span-1" :value="$account->agency" maxlength="20" />
            <x-form.input name="account_number" label="Conta" class="md:col-span-1" :value="$account->account_number" maxlength="30" />

            @if ($method === 'POST')
                <x-form.money name="opening_balance" label="Saldo inicial" class="md:col-span-3" :value="$account->opening_balance_cents" required help="Não poderá ser alterado depois: correções são feitas por lançamento avulso." />
                <x-form.input name="opening_date" type="date" label="Data do saldo inicial" class="md:col-span-3" :value="$account->opening_date?->toDateString()" required />
            @else
                <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600 md:col-span-6 dark:bg-slate-800 dark:text-slate-300">
                    Saldo inicial de {{ money($account->opening_balance_cents) }} em {{ br_date($account->opening_date) }} (imutável — o extrato precisa explicar 100% do saldo).
                </div>
            @endif

            <x-form.checkbox name="is_active" label="Conta ativa (aceita novos lançamentos)" class="md:col-span-6" :checked="$account->is_active" />
        </div>
    </x-ui.card>

    <div class="mt-6 flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
