<x-layouts::app :title="'Gerar contrato — '.$proposal->number">
    <x-ui.page-header title="Gerar contrato a partir da proposta" :subtitle="$proposal->number.' — '.$proposal->customer->display_name" :back="route('proposals.show', $proposal)" />

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('proposals.convert.store', $proposal) }}" class="space-y-6 lg:col-span-2" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <x-ui.card title="Dados do contrato" description="Cliente, itens e valor vêm da proposta — sem redigitar.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="start_date" type="date" label="Início da vigência" :value="today()->toDateString()" required />
                    <x-form.input name="end_date" type="date" label="Término (opcional)" help="Deixe em branco para prazo indeterminado." />
                    <x-form.select name="billing_frequency" label="Periodicidade de cobrança" :options="$frequencies" value="monthly" required />
                    <x-form.input name="billing_day" type="number" min="1" max="28" label="Dia de vencimento" :value="$defaultBillingDay" required help="Entre 1 e 28 (existe em todos os meses)." />
                    <x-form.select name="financial_category_id" label="Categoria financeira das cobranças" :options="$categories" placeholder="Sem categoria" />
                    <div class="space-y-3 pt-6">
                        <x-form.checkbox name="auto_renew" label="Renovação automática ao fim da vigência" />
                        <x-form.checkbox name="activate" label="Ativar o contrato agora" help="Contratos ativos passam a gerar cobranças automaticamente." :checked="true" />
                    </div>
                </div>
            </x-ui.card>
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="route('proposals.show', $proposal)">Cancelar</x-ui.button>
                <x-ui.button variant="success" icon="document-check" x-bind:disabled="submitting">Gerar contrato</x-ui.button>
            </div>
        </form>

        <x-ui.card title="Resumo da proposta">
            <ul class="space-y-2 text-sm">
                @foreach ($proposal->items as $item)
                    <li class="flex justify-between gap-3"><span class="text-slate-600 dark:text-slate-300">{{ $item->description }}</span><span class="tabular-nums">{{ money($item->total_cents) }}</span></li>
                @endforeach
            </ul>
            <div class="mt-4 flex justify-between border-t border-slate-200 pt-3 font-semibold dark:border-slate-700">
                <span>Valor por período</span><span class="tabular-nums">{{ money($proposal->total_cents) }}</span>
            </div>
        </x-ui.card>
    </div>
</x-layouts::app>
