{{--
    Notificações temporárias. Exibe mensagens flash da sessão (success, error,
    warning) e eventos "notify" disparados pelos componentes Livewire:
      $this->dispatch('notify', type: 'success', message: 'Salvo!');
--}}
@php
    $initial = collect(['success', 'error', 'warning', 'status'])
        ->filter(fn ($type) => session()->has($type))
        ->map(fn ($type) => ['type' => $type === 'status' ? 'success' : $type, 'message' => session($type)])
        ->values();

    // Mensagens de status do Fortify chegam como chaves ("verification-link-sent").
    $initial = $initial->map(function ($toast) {
        $toast['message'] = match ($toast['message']) {
            'verification-link-sent' => 'Enviamos um novo link de verificação para o seu e-mail.',
            'profile-information-updated' => 'Perfil atualizado com sucesso.',
            'password-updated' => 'Senha alterada com sucesso.',
            default => $toast['message'],
        };

        return $toast;
    });
@endphp

<div x-data="toasts(@js($initial))" x-on:notify.window="add($event.detail)"
     class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:right-4 sm:left-auto"
     aria-live="polite">
    <template x-for="toast in items" :key="toast.id">
        <div x-transition class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white p-4 shadow-lg dark:bg-slate-800"
             :class="{
                'border-emerald-200 dark:border-emerald-800': toast.type === 'success',
                'border-red-200 dark:border-red-800': toast.type === 'error',
                'border-amber-200 dark:border-amber-800': toast.type === 'warning',
             }" role="status">
            <div class="mt-0.5" :class="{ 'text-emerald-500': toast.type === 'success', 'text-red-500': toast.type === 'error', 'text-amber-500': toast.type === 'warning' }">
                <template x-if="toast.type === 'success'"><x-icon name="check-circle" class="size-5" /></template>
                <template x-if="toast.type !== 'success'"><x-icon name="exclamation-triangle" class="size-5" /></template>
            </div>
            <p class="flex-1 text-sm text-slate-700 dark:text-slate-200" x-text="toast.message"></p>
            <button type="button" x-on:click="remove(toast.id)" class="text-slate-400 hover:text-slate-600" aria-label="Fechar">
                <x-icon name="x-mark" class="size-4" />
            </button>
        </div>
    </template>
</div>
