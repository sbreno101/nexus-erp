{{--
    Versão para impressão (Dompdf). O Dompdf entende só HTML/CSS simples:
    nada de Tailwind, flexbox ou grid — por isso tabelas e CSS inline.
    A fonte "DejaVu Sans" já vem com o Dompdf e tem todos os acentos.
--}}
@php
    $columns = $report->columns();
    $firstColumn = array_key_first($columns);
    $align = fn (array $column): string => $report->alignment($column) === 'right' ? 'right' : 'left';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title() }}</title>
    <style>
        @page { margin: 24px 28px 40px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1e293b; }
        h1 { font-size: 16px; margin: 0 0 2px 0; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #1e293b; padding-bottom: 8px; margin-bottom: 10px; }
        .header td { vertical-align: top; }
        .filters { margin-bottom: 10px; }
        .filters span { margin-right: 14px; }
        table.summary { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; }
        table.summary td { border: 1px solid #cbd5e1; padding: 6px 8px; width: 25%; }
        table.summary .label { font-size: 8px; color: #64748b; text-transform: uppercase; }
        table.summary .value { font-size: 12px; font-weight: bold; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #e2e8f0; font-weight: bold; padding: 5px 4px; border-bottom: 1px solid #94a3b8; }
        table.data td { padding: 4px; border-bottom: 1px solid #e2e8f0; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data tfoot td { font-weight: bold; background: #f1f5f9; border-top: 2px solid #94a3b8; }
        .warning { margin-top: 8px; padding: 6px 8px; border: 1px solid #f59e0b; background: #fffbeb; color: #92400e; }
        .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 8px; color: #64748b; }
    </style>
</head>
<body>
    <table class="header" width="100%">
        <tr>
            <td>
                <h1>{{ $report->title() }}</h1>
                <div class="muted">{{ $report->description() }}</div>
            </td>
            <td style="text-align: right;">
                <strong>{{ $company->display_name }}</strong><br>
                @if ($company->cnpj)
                    <span class="muted">CNPJ {{ document_format($company->cnpj) }}</span><br>
                @endif
                <span class="muted">Gerado em {{ br_date($generatedAt, withTime: true) }}</span>
            </td>
        </tr>
    </table>

    @if ($appliedFilters !== [])
        <div class="filters">
            <strong>Filtros:</strong>
            @foreach ($appliedFilters as $label => $value)
                <span>{{ $label }}: {{ $value }}</span>
            @endforeach
        </div>
    @endif

    @if ($summary !== [])
        <table class="summary">
            <tr>
                @foreach ($summary as $card)
                    <td>
                        <div class="label">{{ $card['label'] }}</div>
                        <div class="value">{{ $card['value'] }}</div>
                        @if (! empty($card['hint']))
                            <div class="muted">{{ $card['hint'] }}</div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if ($rows === [])
        <p class="muted">Nenhum registro encontrado para os filtros selecionados.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th style="text-align: {{ $align($column) }};">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $key => $column)
                            <td style="text-align: {{ $align($column) }};">{{ $report->formatValue($column, $row[$key] ?? null) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if ($totals !== [])
                <tfoot>
                    <tr>
                        @foreach ($columns as $key => $column)
                            <td style="text-align: {{ $align($column) }};">
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
    @endif

    @if ($truncated)
        <div class="warning">
            Atenção: o PDF exibe somente as primeiras {{ number_format($rowLimit, 0, ',', '.') }} linhas. Os totais consideram todos os registros filtrados. Exporte em CSV para obter a lista completa.
        </div>
    @endif

    <div class="footer">Nexus ERP · {{ $company->display_name }} · {{ $report->title() }}</div>
</body>
</html>
