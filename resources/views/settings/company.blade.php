{{--
    Configurações da empresa ativa.
    Variáveis: $company (Company), $settings (CompanySetting), $themeOptions
--}}
@php
    $stateOptions = array_combine(\App\Http\Requests\Customers\BusinessPartyRequest::STATES, \App\Http\Requests\Customers\BusinessPartyRequest::STATES);
    $zipCode = $company->zip_code ? substr($company->zip_code, 0, 5).'-'.substr($company->zip_code, 5) : null;
@endphp

<x-layouts::app title="Configurações da empresa">
    <x-ui.page-header title="Configurações da empresa" :subtitle="$company->display_name">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <x-ui.badge :color="$company->status->color()">{{ $company->status->label() }}</x-ui.badge>
                <span>CNPJ {{ document_format($company->cnpj) }}</span>
            </div>
        </x-slot:meta>
    </x-ui.page-header>

    <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="space-y-6"
        x-data="{ submitting: false }" x-on:submit="submitting = true">
        @csrf
        @method('PUT')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card title="Dados institucionais" description="Aparecem em propostas, contratos e documentos impressos.">
                    <div class="grid gap-4 md:grid-cols-6">
                        <x-form.input name="legal_name" label="Razão social" class="md:col-span-4" :value="$company->legal_name" required maxlength="255" />
                        <x-form.input name="cnpj" label="CNPJ" class="md:col-span-2" :value="document_format($company->cnpj)" required placeholder="00.000.000/0000-00" />
                        <x-form.input name="trade_name" label="Nome fantasia" class="md:col-span-4" :value="$company->trade_name" maxlength="255" />
                        <x-form.input name="state_registration" label="Inscrição estadual" class="md:col-span-2" :value="$company->state_registration" maxlength="30" />
                        <x-form.input name="email" type="email" label="E-mail" class="md:col-span-3" :value="$company->email" maxlength="255" />
                        <x-form.input name="phone" label="Telefone" class="md:col-span-3" :value="$company->phone" maxlength="20" placeholder="(11) 3333-4444" />
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

                <x-ui.card title="Parâmetros operacionais" description="Valores padrão usados ao criar propostas, contratos, ordens de serviço e cobranças.">
                    <div class="grid gap-4 md:grid-cols-4">
                        <x-form.input name="proposal_prefix" label="Prefixo de propostas" :value="$settings->proposal_prefix" required maxlength="10" help="Ex.: PROP-2026-0001" />
                        <x-form.input name="contract_prefix" label="Prefixo de contratos" :value="$settings->contract_prefix" required maxlength="10" />
                        <x-form.input name="service_order_prefix" label="Prefixo de OS" :value="$settings->service_order_prefix" required maxlength="10" />
                        <x-form.input name="ticket_prefix" label="Prefixo de chamados" :value="$settings->ticket_prefix" required maxlength="10" />

                        <x-form.input name="proposal_validity_days" type="number" min="1" max="365" label="Validade das propostas (dias)" class="md:col-span-2" :value="$settings->proposal_validity_days" required />
                        <x-form.input name="default_billing_day" type="number" min="1" max="28" label="Dia de cobrança padrão" :value="$settings->default_billing_day" required help="Entre 1 e 28 (existe em todos os meses)." />
                        <x-form.input name="billing_days_in_advance" type="number" min="0" max="60" label="Antecedência das cobranças" :value="$settings->billing_days_in_advance" required help="Dias antes do vencimento (0 a 60)." />

                        <x-form.textarea name="default_payment_terms" label="Condições de pagamento padrão" class="md:col-span-4" :value="$settings->default_payment_terms" rows="3" maxlength="2000" help="Texto sugerido automaticamente em novas propostas." />
                    </div>
                </x-ui.card>
            </div>

            <div class="space-y-6">
                <x-ui.card title="Logotipo" description="PNG, JPG ou WEBP de até 2 MB.">
                    <div x-data="{ preview: null }" class="space-y-4">
                        <div class="flex h-32 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                            <template x-if="preview">
                                <img x-bind:src="preview" alt="Pré-visualização do novo logotipo" class="max-h-full max-w-full object-contain">
                            </template>
                            @if ($company->logo_path)
                                <img x-show="!preview" src="{{ route('company.logo', $company) }}?v={{ $company->updated_at?->timestamp }}" alt="Logotipo de {{ $company->display_name }}" class="max-h-full max-w-full object-contain">
                            @else
                                <div x-show="!preview" class="text-center text-sm text-slate-400">
                                    <x-icon name="building" class="mx-auto size-8" />
                                    <p class="mt-1">Nenhum logotipo enviado</p>
                                </div>
                            @endif
                        </div>

                        <div>
                            <label for="field-logo" class="form-label">Enviar novo logotipo</label>
                            <input id="field-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                                x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-slate-300 dark:file:bg-brand-500/10 dark:file:text-brand-300">
                            @error('logo') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        @if ($company->logo_path)
                            <x-form.checkbox name="remove_logo" label="Remover logotipo atual" help="Ignorado se um novo arquivo for enviado." />
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card title="Preferências de interface">
                    <div class="space-y-4">
                        <div>
                            <label for="field-primary_color" class="form-label">Cor primária <span class="text-red-500" aria-hidden="true">*</span></label>
                            <div class="flex items-center gap-3" x-data="{ color: @js(old('primary_color', $settings->primary_color)) }">
                                <input type="color" x-model="color" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-800" aria-label="Seletor de cor">
                                <input id="field-primary_color" name="primary_color" x-model="color" required maxlength="7" class="form-control font-mono" placeholder="#4f46e5">
                            </div>
                            @error('primary_color') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <x-form.select name="default_theme" label="Tema padrão" :options="$themeOptions" :value="$settings->default_theme" required help="Usado por usuários que não escolheram um tema próprio." />
                        <x-form.input name="items_per_page" type="number" min="10" max="100" label="Itens por página" :value="$settings->items_per_page" required help="Quantidade de linhas nas listagens (10 a 100)." />
                    </div>
                </x-ui.card>

                <x-ui.card title="Notificações">
                    <div class="space-y-3">
                        <x-form.checkbox name="notify_overdue_receivables" label="Contas a receber vencidas" :checked="$settings->notify_overdue_receivables" />
                        <x-form.checkbox name="notify_ticket_assignment" label="Chamado atribuído a um técnico" :checked="$settings->notify_ticket_assignment" />
                        <x-form.checkbox name="notify_service_order_assignment" label="Ordem de serviço atribuída" :checked="$settings->notify_service_order_assignment" />
                        <x-form.checkbox name="notify_proposal_status" label="Mudança de status de propostas" :checked="$settings->notify_proposal_status" />
                        <x-form.input name="notification_email" type="email" label="E-mail de notificações" class="pt-2" :value="$settings->notification_email" maxlength="255" help="Opcional: recebe uma cópia dos avisos administrativos." />
                    </div>
                </x-ui.card>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('dashboard')">Cancelar</x-ui.button>
            <x-ui.button icon="check" x-bind:disabled="submitting">Salvar configurações</x-ui.button>
        </div>
    </form>
</x-layouts::app>
