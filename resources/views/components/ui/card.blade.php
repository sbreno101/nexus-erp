{{-- Cartão com título opcional e área de ações. --}}
@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
