<x-layouts::app title="Converter lead">
    <x-ui.page-header title="Converter lead em cliente" :subtitle="$lead->name" :back="route('leads.show', $lead)" />

    <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-500/10 dark:text-blue-300">
        <p class="flex items-center gap-2 font-semibold"><x-icon name="information-circle" class="size-5" /> O que acontece na conversão</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            <li>Um novo cliente é criado com os dados abaixo (CPF/CNPJ obrigatório e validado).</li>
            <li>O lead fica marcado como "Convertido" e vinculado ao cliente — o histórico de interações é preservado.</li>
            <li>As oportunidades abertas do lead passam a apontar para o novo cliente.</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('leads.convert.store', $lead) }}" x-data="{ type: @js(old('type', $suggestedType->value)) }">
        @csrf
        <x-ui.card title="Dados do novo cliente">
            <div class="grid gap-4 md:grid-cols-6">
                <div class="md:col-span-2">
                    <span class="form-label">Tipo de pessoa</span>
                    <div class="flex gap-4 pt-2">
                        @foreach (\App\Enums\PersonType::cases() as $personType)
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" name="type" value="{{ $personType->value }}" x-model="type" class="text-brand-600"> {{ $personType->label() }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <x-form.input name="document" label="CPF / CNPJ" class="md:col-span-2" :value="$lead->document" required />
                <x-form.select name="owner_id" label="Responsável" class="md:col-span-2" :options="$users" :value="$lead->owner_id" placeholder="Sem responsável" />
                <x-form.input name="name" label="Nome / Razão social" class="md:col-span-3" :value="$lead->company_name ?? $lead->name" required />
                <x-form.input name="trade_name" label="Nome fantasia" class="md:col-span-3" :value="$lead->company_name" />
                <x-form.input name="email" type="email" label="E-mail" class="md:col-span-3" :value="$lead->email" />
                <x-form.input name="phone" label="Telefone" class="md:col-span-3" :value="$lead->phone" />
                <x-form.textarea name="notes" label="Observações" class="md:col-span-6" :value="$lead->notes" />
            </div>
        </x-ui.card>

        <div class="mt-6 flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('leads.show', $lead)">Cancelar</x-ui.button>
            <x-ui.button variant="success" icon="check-circle">Converter em cliente</x-ui.button>
        </div>
    </form>
</x-layouts::app>
