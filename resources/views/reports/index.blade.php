{{-- Lista de relatórios disponíveis, agrupados por área (só os que o usuário pode abrir). --}}
<x-layouts::app title="Relatórios">
    <x-ui.page-header title="Relatórios" subtitle="Escolha um relatório para visualizar, filtrar e exportar em CSV ou PDF." />

    <div class="space-y-8">
        @foreach ($groups as $group => $reports)
            <section>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $group }}</h2>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $report)
                        <a href="{{ route('reports.show', $report->key()) }}"
                            class="card flex items-start gap-4 p-5 transition hover:border-brand-300 hover:shadow-sm dark:hover:border-brand-700">
                            <div class="rounded-lg bg-brand-50 p-2.5 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                                <x-icon :name="$report->icon()" class="size-6" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-900 dark:text-white">{{ $report->title() }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $report->description() }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts::app>
