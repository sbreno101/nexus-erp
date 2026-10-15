{{-- Variáveis: $expense, $suppliers, $categories, $costCenters, $frequencies, $action, $method --}}
<form method="POST" action="{{ $action }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Despesa recorrente">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="description" label="Descrição" class="md:col-span-4" :value="$expense->description" required maxlength="255" />
            <x-form.money name="amount" label="Valor" class="md:col-span-2" :value="$expense->amount_cents" required />
            <x-form.select name="supplier_id" label="Fornecedor" class="md:col-span-2" :options="$suppliers" :value="$expense->supplier_id" placeholder="Nenhum" />
            <x-form.select name="financial_category_id" label="Categoria" class="md:col-span-2" :options="$categories" :value="$expense->financial_category_id" placeholder="Sem categoria" />
            <x-form.select name="cost_center_id" label="Centro de custo" class="md:col-span-2" :options="$costCenters" :value="$expense->cost_center_id" placeholder="Nenhum" />
            <x-form.select name="frequency" label="Periodicidade" class="md:col-span-2" :options="$frequencies" :value="$expense->frequency" required />
            <x-form.input name="due_day" type="number" min="1" max="28" label="Dia de vencimento" class="md:col-span-2" :value="$expense->due_day" required />
            <x-form.input name="start_date" type="date" label="Início" class="md:col-span-1" :value="$expense->start_date?->toDateString()" required />
            <x-form.input name="end_date" type="date" label="Término" class="md:col-span-1" :value="$expense->end_date?->toDateString()" />
            <x-form.checkbox name="is_active" label="Ativa (gera contas a pagar automaticamente)" class="md:col-span-6" :checked="$expense->is_active" />
        </div>
    </x-ui.card>

    <div class="mt-6 flex items-center justify-between gap-2">
        <div>
            @if ($expense->exists && auth()->user()->can('delete', $expense))
                <x-ui.confirm-action :action="route('recurring-expenses.destroy', $expense)" method="DELETE" title="Remover despesa recorrente" message="As contas a pagar já geradas permanecem." confirm="Remover" icon="trash" size="sm">Remover</x-ui.confirm-action>
            @endif
        </div>
        <div class="flex gap-2">
            <x-ui.button variant="secondary" :href="route('recurring-expenses.index')">Cancelar</x-ui.button>
            <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
        </div>
    </div>
</form>
