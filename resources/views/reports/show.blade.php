{{--
    Visualização genérica de um relatório.
    Tudo aqui é desenhado a partir da "descrição" do relatório (filtros,
    colunas, linhas, totais e resumo) — veja app/Reports/Report.php.
--}}
@php
    $columns = $report->columns();
    $firstColumn = array_key_first($columns);
    $filterDefinitions = $report->filterDefinitions();
    $alignClass = fn (array $column): string => $report->alignment($column) === 'right' ? 'text-right tabular-nums' : 'text-left';
@endphp

<x-layouts::app :title="$report->title()">
    <x-ui.page-header :title="$report->title()" :subtitle="$report->description()" :back="route('reports.index')">
        <x-ui.button variant="secondary" icon="arrow-down-tray" :href="route('reports.export', ['report' => $report->key(), 'format' => 'csv'] + $exportQuery)">Exportar CSV</x-ui.button>
        <x-ui.button variant="secondary" icon="printer" :href="route('reports.export', ['report' => $report->key(), 'format' => 'pdf'] + $exportQuery)">Exportar PDF</x-ui.button>
    </x-ui.page-header>

    {{-- Filtros: formulário GET, assim a URL guarda os filtros (dá para favoritar/compartilhar). --}}
    @if ($filterDefinitions !== [])
        <form method="GET" action="{{ route('reports.show', $report->key()) }}" class="card mb-6 p-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($filterDefinitions as $name => $filter)
                    @if ($filter['type'] === 'select')
                        <x-form.select :name="$name" :label="$filter['label']" :options="$filter['options'] ?? []" :value="$filters[$name] ?? ''" :placeholder="$filter['placeholder'] ?? null" />
                    @elseif ($filter['type'] === 'date')
                        <x-form.input type="date" :name="$name" :label="$filter['label']" :value="$filters[$name] ?? ''" />
                    @else
                        <x-form.input :name="$name" :label="$filter['label']" :value="$filters[$name] ?? ''" maxlength="100" />
                    @endif
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-ui.button icon="magnifying-glass">Aplicar filtros</x-ui.button>
                <x-ui.button variant="ghost" :href="route('reports.show', $report->key())">Limpar</x-ui.button>
            </div>
        </form>
    @endif

    {{-- Cartões de resumo --}}
    @if ($summary !== [])
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($summary as $card)
                <x-ui.stat :label="$card['label']" :value="$card['value']" :icon="$card['icon'] ?? 'chart-bar'" :color="$card['color'] ?? 'brand'" :hint="$card['hint'] ?? null" />
            @endforeach
        </div>
    @endif

    <div class="card">
        @if ($rows === [])
            <x-ui.empty-state icon="chart-bar" title="Nenhum dado encontrado" description="Não há registros para os filtros selecionados. Ajuste o período ou os filtros e tente novamente." />
        @else
            @if ($truncated)
                <div class="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                    <x-icon name="exclamation-triangle" class="size-5 shrink-0" />
                    Exibindo as primeiras {{ number_format($rowLimit, 0, ',', '.') }} linhas. Refine os filtros ou exporte em CSV para obter o resultado completo.
                </div>
            @endif

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th class="{{ $alignClass($column) }}">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($columns as $key => $column)
                                    <td class="{{ $alignClass($column) }}">
                                        @if ($column['type'] === 'badge' && is_array($row[$key] ?? null))
                                            <x-ui.badge :color="$row[$key]['color'] ?? 'gray'">{{ $row[$key]['label'] ?? '' }}</x-ui.badge>
                                        @else
                                            {{ $report->formatValue($column, $row[$key] ?? null) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($totals !== [])
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold dark:border-slate-700 dark:bg-slate-800/50">
                            <tr>
                                @foreach ($columns as $key => $column)
                                    <td class="{{ $alignClass($column) }}">
                                        @if (array_key_exists($key, $totals))
                                            {{ $report->formatValue($column, $totals[$key]) }}
                                        @elseif ($key === $firstColumn)
                                            {{ $totals['_label'] ?? 'Total' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </div>
</x-layouts::app>
