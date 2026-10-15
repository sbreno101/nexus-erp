{{-- Cabeçalho de página com título, subtítulo, link de voltar e ações. --}}
@props(['title', 'subtitle' => null, 'back' => null])

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                <x-icon name="arrow-left" class="size-4" /> Voltar
            </a>
        @endif
        <h1 class="truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
