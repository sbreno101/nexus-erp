{{--
    Formulário compartilhado de empresas (superadministrador).
    Variáveis: $company (Company), $statusOptions, $action, $method
--}}
@php
    $stateOptions = array_combine(\App\Http\Requests\Customers\BusinessPartyRequest::STATES, \App\Http\Requests\Customers\BusinessPartyRequest::STATES);
    $zipCode = $company->zip_code ? substr($company->zip_code, 0, 5).'-'.substr($company->zip_code, 5) : null;
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados institucionais">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.input name="legal_name" label="Razão social" class="md:col-span-4" :value="$company->legal_name" required maxlength="255" />
            <x-form.input name="cnpj" label="CNPJ" class="md:col-span-2" :value="$company->cnpj ? document_format($company->cnpj) : null" required placeholder="00.000.000/0000-00" />
            <x-form.input name="trade_name" label="Nome fantasia" class="md:col-span-4" :value="$company->trade_name" maxlength="255" />
            <x-form.input name="state_registration" label="Inscrição estadual" class="md:col-span-2" :value="$company->state_registration" maxlength="30" />
            <x-form.input name="email" type="email" label="E-mail" class="md:col-span-2" :value="$company->email" maxlength="255" />
            <x-form.input name="phone" label="Telefone" class="md:col-span-2" :value="$company->phone" maxlength="20" placeholder="(11) 3333-4444" />
            <x-form.select name="status" label="Status" class="md:col-span-2" :options="$statusOptions" :value="$company->status" required
                help="Empresas suspensas ou inativas ficam inacessíveis aos usuários." />
        </div>
    </x-ui.card>

    <x-ui.card title="Endereço">
        <div class="grid gap-4 md:grid-cols-12">
            <x-form.input name="zip_code" label="CEP" class="md:col-span-3" :value="$zipCode" maxlength="9" placeholder="00000-000" />
            <x-form.input name="street" label="Logradouro" class="md:col-span-7" :value="$company->street" maxlength="255" />
            <x-form.input name="number" label="Número" class="md:col-span-2" :value="$company->number" maxlength="20" />
            <x-form.input name="complement" label="Complemento" class="md:col-span-4" :value="$company->complement" maxlength="255" />
            <x-form.input name="district" label="Bairro" class="md:col-span-3" :value="$company->district" maxlength="255" />
            <x-form.input name="city" label="Cidade" class="md:col-span-3" :value="$company->city" maxlength="255" />
            <x-form.select name="state" label="UF" class="md:col-span-2" :options="$stateOptions" :value="$company->state" placeholder="—" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="route('admin.companies.index')">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
