{{--
    Formulário compartilhado de clientes e fornecedores.
    Variáveis: $party (Customer|Supplier), $action (URL), $method, $statusOptions, $users (opcional), $cancelUrl
--}}
@php
    $addresses = old('addresses', $party->relationLoaded('addresses') ? $party->addresses->map->only(['id', 'label', 'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state', 'is_primary'])->values()->all() : []);
    $contacts = old('contacts', $party->relationLoaded('contacts') ? $party->contacts->map->only(['id', 'name', 'position', 'email', 'phone', 'is_primary'])->values()->all() : []);
    $nestedErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'addresses.') || str_starts_with($key, 'contacts.'));
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" x-data="{ submitting: false, type: @js(old('type', $party->type?->value ?? 'company')) }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Dados cadastrais">
        <div class="grid gap-4 md:grid-cols-6">
            <div class="md:col-span-2">
                <span class="form-label">Tipo de pessoa <span class="text-red-500">*</span></span>
                <div class="flex gap-4 pt-2">
                    @foreach (\App\Enums\PersonType::cases() as $personType)
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="type" value="{{ $personType->value }}" x-model="type" class="text-brand-600 focus:ring-brand-500">
                            {{ $personType->label() }}
                        </label>
                    @endforeach
                </div>
                @error('type') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-form.input name="document" class="md:col-span-2" :value="$party->document ? $party->formatted_document : null" required
                x-bind:placeholder="type === 'company' ? '00.000.000/0000-00' : '000.000.000-00'"
                help="Validado automaticamente (dígitos verificadores)." label="CPF / CNPJ" />
            <x-form.select name="status" label="Status" class="md:col-span-2" :options="$statusOptions" :value="$party->status" required />

            <x-form.input name="name" class="md:col-span-3" :value="$party->name" required maxlength="255" label="Nome / Razão social" />
            <x-form.input name="trade_name" class="md:col-span-3" :value="$party->trade_name" maxlength="255" label="Nome fantasia / Apelido" />

            <x-form.input name="email" type="email" class="md:col-span-2" label="E-mail" :value="$party->email" />
            <x-form.input name="phone" class="md:col-span-2" label="Telefone" :value="$party->phone" placeholder="(11) 3333-4444" />
            <x-form.input name="mobile" class="md:col-span-2" label="Celular / WhatsApp" :value="$party->mobile" placeholder="(11) 98888-7777" />

            <x-form.input name="state_registration" class="md:col-span-2" label="Inscrição estadual" :value="$party->state_registration" />
            <x-form.input name="website" type="url" class="md:col-span-2" label="Site" :value="$party->website" placeholder="https://" />
            @isset($users)
                <x-form.select name="owner_id" label="Responsável comercial" class="md:col-span-2" :options="$users" :value="$party->owner_id" placeholder="Sem responsável" />
            @endisset

            <x-form.textarea name="notes" label="Observações" class="md:col-span-6" :value="$party->notes" rows="3" />
        </div>
    </x-ui.card>

    @if ($nestedErrors->isNotEmpty())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-300" role="alert">
            <p class="font-semibold">Verifique os endereços e contatos:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($nestedErrors->flatten()->unique() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Endereços: linhas dinâmicas com Alpine.js (enviadas como addresses[0][street], ...) --}}
    <x-ui.card title="Endereços" x-data="{ rows: {{ Js::from($addresses) }} }">
        <x-slot:actions>
            <x-ui.button type="button" size="sm" variant="secondary" icon="plus" x-on:click="rows.push({ label: 'Principal', state: 'SP', is_primary: rows.length === 0 })">Adicionar endereço</x-ui.button>
        </x-slot:actions>

        <p x-show="rows.length === 0" class="text-sm text-slate-500">Nenhum endereço informado.</p>

        <div class="space-y-4">
            <template x-for="(row, index) in rows" :key="index">
                <div class="grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-12 dark:border-slate-700">
                    <input type="hidden" :name="`addresses[${index}][id]`" :value="row.id ?? ''">
                    <div class="md:col-span-2"><label class="form-label">Identificação</label><input class="form-control" :name="`addresses[${index}][label]`" x-model="row.label" maxlength="50"></div>
                    <div class="md:col-span-2"><label class="form-label">CEP</label><input class="form-control" :name="`addresses[${index}][zip_code]`" x-model="row.zip_code" placeholder="00000-000" maxlength="9"></div>
                    <div class="md:col-span-6"><label class="form-label">Logradouro *</label><input class="form-control" :name="`addresses[${index}][street]`" x-model="row.street" required maxlength="255"></div>
                    <div class="md:col-span-2"><label class="form-label">Número</label><input class="form-control" :name="`addresses[${index}][number]`" x-model="row.number" maxlength="20"></div>
                    <div class="md:col-span-3"><label class="form-label">Complemento</label><input class="form-control" :name="`addresses[${index}][complement]`" x-model="row.complement" maxlength="255"></div>
                    <div class="md:col-span-3"><label class="form-label">Bairro</label><input class="form-control" :name="`addresses[${index}][district]`" x-model="row.district" maxlength="255"></div>
                    <div class="md:col-span-3"><label class="form-label">Cidade *</label><input class="form-control" :name="`addresses[${index}][city]`" x-model="row.city" required maxlength="255"></div>
                    <div class="md:col-span-1"><label class="form-label">UF *</label>
                        <select class="form-control" :name="`addresses[${index}][state]`" x-model="row.state" required>
                            @foreach (\App\Http\Requests\Customers\BusinessPartyRequest::STATES as $uf)
                                <option value="{{ $uf }}">{{ $uf }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end justify-between gap-2 md:col-span-2">
                        <label class="inline-flex items-center gap-2 pb-2 text-sm">
                            <input type="checkbox" value="1" :name="`addresses[${index}][is_primary]`" x-model="row.is_primary" class="rounded text-brand-600"> Principal
                        </label>
                        <button type="button" x-on:click="rows.splice(index, 1)" class="mb-1 rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remover endereço"><x-icon name="trash" class="size-4" /></button>
                    </div>
                </div>
            </template>
        </div>
    </x-ui.card>

    <x-ui.card title="Contatos responsáveis" x-data="{ rows: {{ Js::from($contacts) }} }">
        <x-slot:actions>
            <x-ui.button type="button" size="sm" variant="secondary" icon="plus" x-on:click="rows.push({ is_primary: rows.length === 0 })">Adicionar contato</x-ui.button>
        </x-slot:actions>

        <p x-show="rows.length === 0" class="text-sm text-slate-500">Nenhum contato informado.</p>

        <div class="space-y-3">
            <template x-for="(row, index) in rows" :key="index">
                <div class="grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-12 dark:border-slate-700">
                    <input type="hidden" :name="`contacts[${index}][id]`" :value="row.id ?? ''">
                    <div class="md:col-span-3"><label class="form-label">Nome *</label><input class="form-control" :name="`contacts[${index}][name]`" x-model="row.name" required maxlength="255"></div>
                    <div class="md:col-span-3"><label class="form-label">Cargo</label><input class="form-control" :name="`contacts[${index}][position]`" x-model="row.position" maxlength="255"></div>
                    <div class="md:col-span-3"><label class="form-label">E-mail</label><input type="email" class="form-control" :name="`contacts[${index}][email]`" x-model="row.email" maxlength="255"></div>
                    <div class="md:col-span-2"><label class="form-label">Telefone</label><input class="form-control" :name="`contacts[${index}][phone]`" x-model="row.phone" maxlength="20"></div>
                    <div class="flex items-end justify-between md:col-span-1">
                        <label class="inline-flex items-center gap-1 pb-2 text-xs"><input type="checkbox" value="1" :name="`contacts[${index}][is_primary]`" x-model="row.is_primary" class="rounded text-brand-600"> Princ.</label>
                        <button type="button" x-on:click="rows.splice(index, 1)" class="mb-1 rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remover contato"><x-icon name="trash" class="size-4" /></button>
                    </div>
                </div>
            </template>
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button icon="check" x-bind:disabled="submitting">Salvar</x-ui.button>
    </div>
</form>
