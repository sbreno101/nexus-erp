{{--
    Formulário compartilhado de contas a receber ($kind = 'receivable') e a pagar ($kind = 'payable').
    Variáveis: $bill, $kind, $parties, $contracts, $categories, $costCenters, $action, $method, $cancelUrl
--}}
@php($isReceivable = $kind === 'receivable')

<form method="POST" action="{{ $action }}" class="space-y-6" x-data="{ submitting: false, partyId: @js((string) old($isReceivable ? 'customer_id' : 'supplier_id', $bill->{$isReceivable ? 'customer_id' : 'supplier_id'})) }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card :title="$isReceivable ? 'Título a receber' : 'Despesa a pagar'">
        <div class="grid gap-4 md:grid-cols-6">
            @if ($isReceivable)
                <x-form.select name="customer_id" label="Cliente" class="md:col-span-3" :options="$parties" :value="$bill->customer_id" placeholder="Selecione" required x-model="partyId" />
                <div class="md:col-span-3">
                    <label class="form-label" for="field-contract_id">Contrato (opcional)</label>
                    <select id="field-contract_id" name="contract_id" class="form-control">
                        <option value="">Sem contrato</option>
                        @foreach ($contracts as $contract)
                            <option value="{{ $contract['id'] }}" x-show="partyId === '{{ $contract['customer_id'] }}'" @selected((string) old('contract_id', $bill->contract_id) === (string) $contract['id'])>{{ $contract['label'] }}</option>
                        @endforeach
                    </select>
                    @error('contract_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @else
                <x-form.select name="supplier_id" label="Fornecedor" class="md:col-span-3" :options="$parties" :value="$bill->supplier_id" placeholder="Sem fornecedor (ex.: impostos)" />
                <x-form.input name="document_number" label="Nº do documento (NF, boleto)" class="md:col-span-3" :value="$bill->document_number" maxlength="50" />
            @endif

            <x-form.input name="description" label="Descrição" class="md:col-span-6" :value="$bill->description" required maxlength="255" />
            <x-form.money name="amount" label="Valor" class="md:col-span-2" :value="$bill->amount_cents" required />
            <x-form.input name="issue_date" type="date" label="Emissão" class="md:col-span-2" :value="$bill->issue_date?->toDateString()" required />
            <x-form.input name="due_date" type="date" label="Vencimento" class="md:col-span-2" :value="$bill->due_date?->toDateString()" required />
            <x-form.input name="competence" type="month" label="Competência" class="md:col-span-2" :value="$bill->competence?->format('Y-m')" help="Mês de referência (vazio = mês do vencimento)." />
            <x-form.select name="financial_category_id" label="Categoria" class="md:col-span-2" :options="$categories" :value="$bill->financial_category_id" placeholder="Sem categoria" />
            <x-form.select name="cost_center_id" label="Centro de custo" class="md:col-span-2" :options="$costCenters" :value="$bill->cost_center_id" placeholder="Nenhum" />
            @if ($isReceivable)
                <x-form.input name="document_number" label="Nº do documento" class="md:col-span-2" :value="$bill->document_number" maxlength="50" />
            @endif
            <x-form.textarea name="notes" label="Observações" class="md:col-span-6" :value="$bill->notes" rows="2" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
