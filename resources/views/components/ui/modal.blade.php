{{--
    Modal acessível controlado por Alpine. Abra disparando o evento:
      <button x-data x-on:click="$dispatch('open-modal', 'nome-do-modal')">Abrir</button>
      <x-ui.modal name="nome-do-modal" title="Título"> ... </x-ui.modal>
--}}
@props(['name', 'title' => null, 'maxWidth' => 'lg'])

@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" x-on:click="open = false"></div>

    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
        <div
            x-show="open"
            x-transition
            x-trap.noscroll="open"
            class="relative w-full {{ $widths[$maxWidth] ?? $widths['lg'] }} rounded-xl bg-white shadow-xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800"
        >
            @if ($title)
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                    <button type="button" x-on:click="open = false" class="rounded-md p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" aria-label="Fechar">
                        <x-icon name="x-mark" class="size-5" />
                    </button>
                </div>
            @endif
            <div class="px-5 py-4">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
