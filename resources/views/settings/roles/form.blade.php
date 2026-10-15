{{--
    Formulário compartilhado de perfis de acesso.
    Variáveis: $role (Role), $groups (config permissions.groups: grupo => [nome => rótulo]),
               $selected (list<string>), $action, $method, $readOnly (bool)
--}}
@php
    $selected = old('permissions', $selected);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <x-ui.card title="Identificação">
        <div class="grid gap-4 md:grid-cols-3">
            <x-form.input name="name" label="Nome do perfil" :value="$role->name" required maxlength="100" :disabled="$readOnly" />
            <x-form.input name="description" label="Descrição" class="md:col-span-2" :value="$role->description" maxlength="255" :disabled="$readOnly"
                placeholder="Ex.: Equipe que emite cobranças e registra pagamentos" />
        </div>
    </x-ui.card>

    <x-ui.card title="Permissões" description="Marque o que os usuários com este perfil podem acessar.">
        @error('permissions') <p class="form-error mb-3">{{ $message }}</p> @enderror
        @error('permissions.*') <p class="form-error mb-3">{{ $message }}</p> @enderror

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($groups as $group => $permissions)
                {{-- Cada grupo tem seu próprio escopo Alpine: os botões marcam/desmarcam apenas as caixas dele. --}}
                <fieldset class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                    x-data="{
                        setAll(value) { this.$root.querySelectorAll('input[data-permission]').forEach((input) => input.checked = value) },
                    }">
                    <legend class="px-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $group }}</legend>

                    @unless ($readOnly)
                        <div class="mb-3 flex gap-3 text-xs">
                            <button type="button" class="link" x-on:click="setAll(true)">Marcar todos</button>
                            <button type="button" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" x-on:click="setAll(false)">Desmarcar todos</button>
                        </div>
                    @endunless

                    <div class="space-y-2">
                        @foreach ($permissions as $permission => $label)
                            <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" data-permission
                                    class="mt-0.5 size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
                                    @checked(in_array($permission, $selected, true)) @disabled($readOnly)>
                                <span>
                                    {{ $label }}
                                    <span class="block font-mono text-[11px] text-slate-400">{{ $permission }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="route('roles.index')">{{ $readOnly ? 'Voltar' : 'Cancelar' }}</x-ui.button>
        @unless ($readOnly)
            <x-ui.button icon="check" x-bind:disabled="submitting">Salvar perfil</x-ui.button>
        @endunless
    </div>
</form>
