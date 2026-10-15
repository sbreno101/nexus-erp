{{-- Formulário de lead (create/edit). Variáveis: $lead, $action, $method, $sources, $users, $statusOptions, $cancelUrl --}}
<form method="POST" action="{{ $action }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados do lead">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="name" label="Nome do contato" class="md:col-span-3" :value="$lead->name" required maxlength="255" />
            <x-form.input name="company_name" label="Empresa" class="md:col-span-3" :value="$lead->company_name" maxlength="255" />
            <x-form.input name="email" type="email" label="E-mail" class="md:col-span-2" :value="$lead->email" />
            <x-form.input name="phone" label="Telefone" class="md:col-span-2" :value="$lead->phone" />
            <x-form.input name="document" label="CPF/CNPJ (opcional)" class="md:col-span-2" :value="$lead->document" />
            <x-form.select name="lead_source_id" label="Origem" class="md:col-span-2" :options="$sources" :value="$lead->lead_source_id" placeholder="Não informada" />
            <x-form.select name="status" label="Status" class="md:col-span-2" :options="$statusOptions" :value="$lead->status" required />
            <x-form.select name="owner_id" label="Responsável" class="md:col-span-2" :options="$users" :value="$lead->owner_id" placeholder="Sem responsável" />
            <x-form.money name="estimated_value" label="Valor estimado" class="md:col-span-2" :value="$lead->estimated_value_cents" />
            <x-form.input name="next_contact_at" type="date" label="Data de retorno" class="md:col-span-2" :value="$lead->next_contact_at?->toDateString()" />
            <x-form.textarea name="notes" label="Observações" class="md:col-span-6" :value="$lead->notes" />
        </div>
    </x-ui.card>

    <div class="mt-6 flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
