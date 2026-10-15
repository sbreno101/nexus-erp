{{--
    Formulário compartilhado de unidades.
    Variáveis: $unit (CompanyUnit), $action, $method
--}}
@php
    $stateOptions = array_combine(\App\Http\Requests\Customers\BusinessPartyRequest::STATES, \App\Http\Requests\Customers\BusinessPartyRequest::STATES);
    $zipCode = $unit->zip_code ? substr($unit->zip_code, 0, 5).'-'.substr($unit->zip_code, 5) : null;
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados da unidade">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="name" label="Nome" class="md:col-span-3" :value="$unit->name" required maxlength="255" placeholder="Ex.: Filial Campinas" />
            <x-form.input name="code" label="Código" class="md:col-span-1" :value="$unit->code" required maxlength="20" help="Único na empresa." />
            <x-form.input name="cnpj" label="CNPJ" class="md:col-span-2" :value="$unit->cnpj ? document_format($unit->cnpj) : null" placeholder="00.000.000/0000-00" help="Opcional (CNPJ da filial)." />
            <x-form.input name="email" type="email" label="E-mail" class="md:col-span-3" :value="$unit->email" maxlength="255" />
            <x-form.input name="phone" label="Telefone" class="md:col-span-3" :value="$unit->phone" maxlength="20" placeholder="(11) 3333-4444" />
            <x-form.checkbox name="is_active" label="Unidade ativa" class="md:col-span-6" :checked="$unit->is_active"
                help="Unidades inativas não aparecem para seleção em novos usuários e contratos." />
        </div>
    </x-ui.card>

    <x-ui.card title="Endereço">
        <div class="grid gap-4 md:grid-cols-12">
            <x-form.input name="zip_code" label="CEP" class="md:col-span-3" :value="$zipCode" maxlength="9" placeholder="00000-000" />
            <x-form.input name="street" label="Logradouro" class="md:col-span-7" :value="$unit->street" maxlength="255" />
            <x-form.input name="number" label="Número" class="md:col-span-2" :value="$unit->number" maxlength="20" />
            <x-form.input name="complement" label="Complemento" class="md:col-span-4" :value="$unit->complement" maxlength="255" />
            <x-form.input name="district" label="Bairro" class="md:col-span-3" :value="$unit->district" maxlength="255" />
            <x-form.input name="city" label="Cidade" class="md:col-span-3" :value="$unit->city" maxlength="255" />
            <x-form.select name="state" label="UF" class="md:col-span-2" :options="$stateOptions" :value="$unit->state" placeholder="—" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="route('units.index')">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
