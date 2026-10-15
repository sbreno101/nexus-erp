<div>
    <x-ui.page-header title="Funil de vendas" subtitle="Arraste os cartões entre as etapas — as mudanças são salvas automaticamente.">
        <x-ui.button variant="secondary" icon="clipboard-list" :href="route('opportunities.index')">Ver em tabela</x-ui.button>
        @can('create', \App\Models\Opportunity::class)
            <x-ui.button icon="plus" :href="route('opportunities.create')">Nova oportunidade</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar oportunidade..." class="form-control w-full sm:max-w-xs" aria-label="Buscar oportunidade">
        <select wire:model.live="owner" class="form-control w-auto" aria-label="Responsável">
            <option value="">Todos os responsáveis</option>
            <option value="me">Minhas oportunidades</option>
            @foreach ($owners as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" wire:model.live="showClosed" class="rounded text-brand-600"> Mostrar encerradas (30 dias)
        </label>
        <span wire:loading class="text-xs text-slate-500">Salvando…</span>
    </div>

    <div class="flex gap-4 overflow-x-auto pb-4">
        @foreach ($stages as $stage)
            @php($cards = $opportunities->get($stage->id, collect()))
            <section wire:key="stage-{{ $stage->id }}" class="flex w-72 shrink-0 flex-col rounded-xl bg-slate-100 dark:bg-slate-900/60" aria-label="Etapa {{ $stage->name }}">
                <header class="flex items-center justify-between gap-2 px-3 py-3">
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full" style="background: {{ $stage->color }}"></span>
                        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $stage->name }}</h2>
                        <span class="rounded-full bg-white px-2 text-xs text-slate-500 dark:bg-slate-800">{{ $cards->count() }}</span>
                    </div>
                    <span class="text-xs font-medium text-slate-500">{{ money((int) $cards->sum('estimated_value_cents')) }}</span>
                </header>

                <div class="flex min-h-24 flex-1 flex-col gap-2 px-2 pb-3"
                     @if ($canUpdate) x-sort="$wire.move($item, $position, {{ $stage->id }})" x-sort:group="pipeline" @endif>
                    @foreach ($cards as $opportunity)
                        <article wire:key="opportunity-{{ $opportunity->id }}" x-sort:item="{{ $opportunity->id }}"
                            @class(['rounded-lg border border-slate-200 bg-white p-3 shadow-xs dark:border-slate-700 dark:bg-slate-800', 'cursor-grab active:cursor-grabbing' => $canUpdate])>
                            <a href="{{ route('opportunities.show', $opportunity) }}" class="block text-sm font-medium text-slate-900 hover:text-brand-600 dark:text-white">{{ $opportunity->title }}</a>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $opportunity->counterpartName() }}</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ money($opportunity->estimated_value_cents) }}</span>
                                <span class="text-xs text-slate-500">{{ $opportunity->probability }}%</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                                <span>{{ $opportunity->owner?->name ?? 'Sem responsável' }}</span>
                                @if ($opportunity->expected_close_date)
                                    <span @class(['font-semibold text-red-600' => $opportunity->status->value === 'open' && $opportunity->expected_close_date->isPast()])>{{ $opportunity->expected_close_date->format('d/m') }}</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
