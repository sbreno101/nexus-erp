{{-- Formulário de proposta. Variáveis: $proposal, $items, $customers, $opportunities, $users, $catalog, $action, $method, $cancelUrl --}}
<form method="POST" action="{{ $action }}" class="space-y-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados da proposta">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="title" label="Título" class="md:col-span-6" :value="$proposal->title" required maxlength="255" placeholder="Ex.: Suporte técnico mensal — Plano Avançado" />
            <x-form.select name="customer_id" label="Cliente" class="md:col-span-3" :options="$customers" :value="$proposal->customer_id" placeholder="Selecione o cliente" required />
            <x-form.select name="opportunity_id" label="Oportunidade (CRM)" class="md:col-span-3" :options="$opportunities" :value="$proposal->opportunity_id" placeholder="Nenhuma" help="Ao aprovar, a oportunidade é marcada como ganha." />
            <x-form.select name="owner_id" label="Responsável" class="md:col-span-2" :options="$users" :value="$proposal->owner_id" placeholder="Eu" />
            <x-form.input name="issue_date" type="date" label="Data de emissão" class="md:col-span-2" :value="$proposal->issue_date?->toDateString()" />
            <x-form.input name="valid_until" type="date" label="Válida até" class="md:col-span-2" :value="$proposal->valid_until?->toDateString()" />
        </div>
    </x-ui.card>

    <x-ui.card title="Itens" description="Produtos e serviços do catálogo ou itens livres.">
        <x-line-items-editor :items="$items" :catalog="$catalog" :discount="money($proposal->discount_cents ?? 0, false)" />
    </x-ui.card>

    <x-ui.card title="Condições">
        <div class="grid gap-4 md:grid-cols-2">
            <x-form.textarea name="payment_terms" label="Condições comerciais" :value="$proposal->payment_terms" rows="4" placeholder="Forma de pagamento, reajuste, prazos..." />
            <x-form.textarea name="notes" label="Observações" :value="$proposal->notes" rows="4" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar proposta</x-ui.button>
    </div>
</form>
