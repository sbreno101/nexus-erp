{{--
    Formulário compartilhado de abertura/edição de chamados.
    Variáveis: $ticket, $customers, $contracts, $serviceOrders, $categories, $users,
               $priorityOptions, $channelOptions, $action (URL), $method, $cancelUrl

    Contratos e OS são filtrados no navegador (Alpine.js) para mostrar só os
    do cliente escolhido. O servidor valida de novo.
--}}
@php
    $selectedCustomer = (string) old('customer_id', $ticket->customer_id);
    $selectedContract = (string) old('contract_id', $ticket->contract_id);
    $selectedServiceOrder = (string) old('service_order_id', $ticket->service_order_id);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
    x-data="{ submitting: false, customer: @js($selectedCustomer), contract: @js($selectedContract), serviceOrder: @js($selectedServiceOrder) }"
    x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados do chamado">
        <div class="grid gap-4 md:grid-cols-6">
            <x-form.select name="customer_id" label="Cliente" class="md:col-span-2" :options="$customers" :value="$selectedCustomer"
                placeholder="Selecione o cliente" required x-model="customer" x-on:change="contract = ''; serviceOrder = ''" />

            <div class="md:col-span-2">
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
                @error('contract_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label for="field-service_order_id" class="form-label">Ordem de serviço</label>
                <select id="field-service_order_id" name="service_order_id" x-model="serviceOrder"
                    @class(['form-control', 'form-control-invalid' => $errors->has('service_order_id')])>
                    <option value="">Sem OS vinculada</option>
                    @foreach ($serviceOrders as $serviceOrderOption)
                        <option value="{{ $serviceOrderOption->id }}" x-show="customer === @js((string) $serviceOrderOption->customer_id)" x-bind:disabled="customer !== @js((string) $serviceOrderOption->customer_id)">
                            {{ $serviceOrderOption->number }} — {{ \Illuminate\Support\Str::limit($serviceOrderOption->title, 40) }}
                        </option>
                    @endforeach
                </select>
                @error('service_order_id')
                    <p class="form-error">{{ $message }}</p>
                @else
                    <p class="form-help">OS pendentes do cliente selecionado.</p>
                @enderror
            </div>

            <x-form.input name="subject" label="Assunto" class="md:col-span-6" :value="$ticket->subject" required maxlength="255" />

            <x-form.select name="ticket_category_id" label="Categoria" class="md:col-span-2" :options="$categories" :value="$ticket->ticket_category_id" placeholder="Sem categoria" />
            <x-form.select name="channel" label="Canal" class="md:col-span-1" :options="$channelOptions" :value="$ticket->channel" required />
            <x-form.select name="priority" label="Prioridade" class="md:col-span-1" :options="$priorityOptions" :value="$ticket->priority" required />
            <x-form.select name="assigned_to" label="Responsável" class="md:col-span-2" :options="$users" :value="$ticket->assigned_to" placeholder="Sem responsável" help="O responsável é notificado ao ser designado." />

            <x-form.textarea name="description" label="Descrição da solicitação" class="md:col-span-6" :value="$ticket->description" rows="6" required maxlength="5000" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
