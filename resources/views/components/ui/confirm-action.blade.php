{{--
    Botão que abre uma confirmação antes de enviar uma ação irreversível.
    Opcionalmente pede um motivo (campo "reason").

      <x-ui.confirm-action :action="route('contracts.status', $contract)" method="PATCH"
          title="Cancelar contrato" message="As cobranças futuras serão canceladas."
          confirm="Cancelar contrato" variant="danger" icon="no-symbol" reason>
          <x-slot:fields><input type="hidden" name="status" value="cancelled"></x-slot:fields>
          Cancelar
      </x-ui.confirm-action>
--}}
@props([
    'action',
    'method' => 'POST',
    'title' => 'Confirmar ação',
    'message' => 'Tem certeza que deseja continuar?',
    'confirm' => 'Confirmar',
    'variant' => 'danger',
    'icon' => null,
    'size' => 'md',
    'reason' => false,
    'reasonLabel' => 'Motivo',
    'reasonRequired' => true,
])

@php($modalName = 'confirm-'.md5($action.$title.$confirm))

<x-ui.button type="button" :variant="$variant === 'danger' ? 'secondary' : $variant" :size="$size" :icon="$icon"
    x-data x-on:click="$dispatch('open-modal', '{{ $modalName }}')" {{ $attributes }}>
    {{ $slot }}
</x-ui.button>

<x-ui.modal :name="$modalName" :title="$title" max-width="md">
    <form method="POST" action="{{ $action }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
        @csrf
        @unless (strtoupper($method) === 'POST')
            @method($method)
        @endunless
        {{ $fields ?? '' }}

        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $message }}</p>

        @if ($reason)
            <x-form.textarea name="reason" :label="$reasonLabel" rows="3" class="mt-4" :required="$reasonRequired" maxlength="500" />
        @endif

        <div class="mt-6 flex justify-end gap-2">
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">Voltar</x-ui.button>
            <x-ui.button :variant="$variant" x-bind:disabled="submitting">
                <span x-show="!submitting">{{ $confirm }}</span>
                <span x-show="submitting" x-cloak>Processando…</span>
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
