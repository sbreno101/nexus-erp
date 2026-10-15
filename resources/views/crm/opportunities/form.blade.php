{{-- Formulário de oportunidade. Variáveis: $opportunity, $action, $method, $customers, $leads, $stages, $users, $cancelUrl --}}
<form method="POST" action="{{ $action }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Oportunidade">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="title" label="Título" class="md:col-span-6" :value="$opportunity->title" required maxlength="255" placeholder="Ex.: Contrato de suporte anual" />
            <x-form.select name="customer_id" label="Cliente" class="md:col-span-3" :options="$customers" :value="$opportunity->customer_id" placeholder="Selecione (ou informe um lead)" help="Informe o cliente ou o lead." />
            <x-form.select name="lead_id" label="Lead" class="md:col-span-3" :options="$leads" :value="$opportunity->lead_id" placeholder="Nenhum" />
            <x-form.select name="pipeline_stage_id" label="Etapa do funil" class="md:col-span-2" :options="$stages" :value="$opportunity->pipeline_stage_id" required />
            <x-form.select name="owner_id" label="Responsável" class="md:col-span-2" :options="$users" :value="$opportunity->owner_id" placeholder="Sem responsável" />
            <x-form.input name="expected_close_date" type="date" label="Previsão de fechamento" class="md:col-span-2" :value="$opportunity->expected_close_date?->toDateString()" />
            <x-form.money name="estimated_value" label="Valor estimado" class="md:col-span-2" :value="$opportunity->estimated_value_cents" required />
            <x-form.input name="probability" type="number" min="0" max="100" label="Probabilidade (%)" class="md:col-span-2" :value="$opportunity->probability" help="Vazio = padrão da etapa." />
            <x-form.input name="lost_reason" label="Motivo da perda" class="md:col-span-2" :value="$opportunity->lost_reason" help="Preencha ao mover para 'Perdida'." />
            <x-form.textarea name="description" label="Descrição" class="md:col-span-6" :value="$opportunity->description" />
        </div>
    </x-ui.card>

    <div class="mt-6 flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
