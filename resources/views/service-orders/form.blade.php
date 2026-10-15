{{--
    Formulário compartilhado de abertura/edição de ordens de serviço.
    Variáveis: $serviceOrder, $customers, $contracts, $services, $users, $priorityOptions,
               $action (URL), $method, $cancelUrl

    A lista de contratos mostra apenas os do cliente escolhido (filtro no
    navegador com Alpine.js). O servidor valida de novo: contrato precisa
    ser do mesmo cliente.
--}}
@php
    $selectedCustomer = (string) old('customer_id', $serviceOrder->customer_id);
    $selectedContract = (string) old('contract_id', $serviceOrder->contract_id);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
    x-data="{ submitting: false, customer: @js($selectedCustomer), contract: @js($selectedContract) }"
    x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados da ordem de serviço">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.select name="customer_id" label="Cliente" class="md:col-span-3" :options="$customers" :value="$selectedCustomer"
                placeholder="Selecione o cliente" required x-model="customer" x-on:change="contract = ''" />

            <div class="md:col-span-3">
                <label for="field-contract_id" class="form-label">Contrato</label>
                <select id="field-contract_id" name="contract_id" x-model="contract"
                    @class(['form-control', 'form-control-invalid' => $errors->has('contract_id')])>
                    <option value="">Sem contrato vinculado</option>
                    @foreach ($contracts as $contract)
                        <option value="{{ $contract->id }}" x-show="customer === @js((string) $contract->customer_id)" x-bind:disabled="customer !== @js((string) $contract->customer_id)">
                            {{ $contract->number }} — {{ $contract->customer?->display_name }}
                        </option>
                    @endforeach
                </select>
                @error('contract_id')
                    <p class="form-error">{{ $message }}</p>
                @else
                    <p class="form-help">Somente contratos do cliente selecionado.</p>
                @enderror
            </div>

            <x-form.input name="title" label="Título" class="md:col-span-4" :value="$serviceOrder->title" required maxlength="255" />
            <x-form.select name="priority" label="Prioridade" class="md:col-span-2" :options="$priorityOptions" :value="$serviceOrder->priority" required />

            <x-form.select name="catalog_item_id" label="Serviço do catálogo" class="md:col-span-2" :options="$services" :value="$serviceOrder->catalog_item_id" placeholder="Nenhum" />
            <x-form.select name="assigned_to" label="Responsável" class="md:col-span-2" :options="$users" :value="$serviceOrder->assigned_to" placeholder="Sem responsável" help="O responsável é notificado ao ser designado." />
            <x-form.input name="due_date" type="date" label="Prazo" class="md:col-span-2" :value="$serviceOrder->due_date?->format('Y-m-d')" />

            <x-form.textarea name="description" label="Descrição do serviço" class="md:col-span-6" :value="$serviceOrder->description" rows="5" required maxlength="5000" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
