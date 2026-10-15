{{-- Cartão de indicador (KPI) do dashboard. --}}
@props(['label', 'value', 'icon' => 'chart-bar', 'color' => 'brand', 'hint' => null, 'href' => null])

@php
    $iconColors = [
        'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300',
        'green' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
        'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
        'yellow' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
        'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',
        'purple' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-300',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card flex items-start gap-4 p-5'.($href ? ' transition hover:border-brand-300 hover:shadow-sm dark:hover:border-brand-700' : '')]) }}>
    <div class="rounded-lg p-2.5 {{ $iconColors[$color] ?? $iconColors['brand'] }}">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <div class="min-w-0">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
        <p class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
