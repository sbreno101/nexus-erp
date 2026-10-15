{{--
    Botão/link padrão. Exemplos:
      <x-ui.button>Salvar</x-ui.button>                         (submit)
      <x-ui.button variant="secondary" :href="route('x')">Voltar</x-ui.button>
      <x-ui.button variant="danger" icon="trash" size="sm">Excluir</x-ui.button>
--}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'icon' => null, 'type' => 'submit'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-900';
    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-base',
    ];
    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-xs hover:bg-brand-700',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700',
        'danger' => 'bg-red-600 text-white shadow-xs hover:bg-red-700',
        'success' => 'bg-emerald-600 text-white shadow-xs hover:bg-emerald-700',
        'warning' => 'bg-amber-500 text-white shadow-xs hover:bg-amber-600',
        'ghost' => 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
