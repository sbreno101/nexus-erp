{{-- Formulário de contrato. Variáveis: $contract, $items, $customers, $units, $users, $frequencies, $categories, $catalog, $action, $method, $cancelUrl --}}
<form method="POST" action="{{ $action }}" class="space-y-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados do contrato">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="title" label="Título" class="md:col-span-4" :value="$contract->title" required maxlength="255" />
            <x-form.select name="customer_id" label="Cliente" class="md:col-span-2" :options="$customers" :value="$contract->customer_id" placeholder="Selecione" required />
            <x-form.select name="company_unit_id" label="Unidade responsável" class="md:col-span-2" :options="$units" :value="$contract->company_unit_id" placeholder="Empresa (sem unidade)" />
            <x-form.select name="owner_id" label="Responsável" class="md:col-span-2" :options="$users" :value="$contract->owner_id" placeholder="Eu" />
            <x-form.input name="signed_at" type="date" label="Data de assinatura" class="md:col-span-2" :value="$contract->signed_at?->toDateString()" />
            <x-form.textarea name="description" label="Escopo / descrição" class="md:col-span-6" :value="$contract->description" />
        </div>
    </x-ui.card>

    <x-ui.card title="Vigência e cobrança">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="start_date" type="date" label="Início" class="md:col-span-2" :value="$contract->start_date?->toDateString()" required />
            <x-form.input name="end_date" type="date" label="Término" class="md:col-span-2" :value="$contract->end_date?->toDateString()" help="Vazio = prazo indeterminado." />
            <x-form.select name="billing_frequency" label="Periodicidade" class="md:col-span-2" :options="$frequencies" :value="$contract->billing_frequency" required />
            <x-form.input name="billing_day" type="number" min="1" max="28" label="Dia de vencimento" class="md:col-span-2" :value="$contract->billing_day" required />
            <x-form.select name="financial_category_id" label="Categoria financeira" class="md:col-span-2" :options="$categories" :value="$contract->financial_category_id" placeholder="Sem categoria" />
            <x-form.input name="renewal_months" type="number" min="1" max="120" label="Renovar por (meses)" class="md:col-span-2" :value="$contract->renewal_months" required />
            <x-form.checkbox name="auto_renew" label="Renovação automática ao fim da vigência" class="md:col-span-6" :checked="$contract->auto_renew" />
        </div>
    </x-ui.card>

    <x-ui.card title="Serviços contratados" description="O valor por período do contrato é a soma dos itens.">
        <x-line-items-editor :items="$items" :catalog="$catalog" />
    </x-ui.card>

    <x-ui.card title="Observações">
        <x-form.textarea name="notes" :value="$contract->notes" rows="3" />
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar contrato</x-ui.button>
    </div>
</form>
