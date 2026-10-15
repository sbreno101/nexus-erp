<?php

namespace App\Http\Controllers;

use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Support\CsvExporter;
use App\Support\Tenancy\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tela de relatórios (lista, visualização e exportação CSV/PDF).
 *
 * O controller é genérico: quem sabe O QUE consultar é cada classe de
 * relatório (app/Reports). Aqui só resolvemos o relatório pela chave da URL,
 * conferimos a permissão e entregamos o resultado no formato pedido.
 */
class ReportController extends Controller
{
    /**
     * Linhas exibidas na tela. Acima disso a página ficaria pesada: o usuário
     * é orientado a exportar em CSV (que não tem limite, pois é gerado em streaming).
     */
    private const int SCREEN_ROW_LIMIT = 1000;

    /**
     * Linhas no PDF: o Dompdf monta o documento inteiro na memória.
     */
    private const int PDF_ROW_LIMIT = 2000;

    public function __construct(
        private ReportRegistry $registry,
        private CurrentCompany $currentCompany,
    ) {}

    public function index(Request $request): View
    {
        $groups = $this->registry->groupedFor($request->user());

        abort_if($groups->isEmpty(), Response::HTTP_FORBIDDEN);

        return view('reports.index', ['groups' => $groups]);
    }

    public function show(Request $request, string $report): View
    {
        $report = $this->authorizedReport($request, $report);
        $filters = $report->normalizeFilters($request->query());
        [$rows, $truncated] = $this->limitedRows($report, $filters, self::SCREEN_ROW_LIMIT);

        return view('reports.show', [
            'report' => $report,
            'filters' => $filters,
            'rows' => $rows,
            'truncated' => $truncated,
            'rowLimit' => self::SCREEN_ROW_LIMIT,
            'totals' => $report->totals($filters),
            'summary' => $report->summary($filters),
            'exportQuery' => $this->queryString($filters),
        ]);
    }

    public function export(Request $request, string $report, string $format): Response
    {
        $report = $this->authorizedReport($request, $report);
        $filters = $report->normalizeFilters($request->query());
        $filename = $report->key().'-'.now()->format('Y-m-d-His');

        return $format === 'pdf'
            ? $this->exportPdf($report, $filters, $filename.'.pdf')
            : $this->exportCsv($report, $filters, $filename.'.csv');
    }

    /**
     * Resolve o relatório (404 se a chave não existe) e exige a permissão dele (403).
     */
    private function authorizedReport(Request $request, string $key): Report
    {
        $report = $this->registry->resolve($key);

        abort_unless($request->user()->can($report->permission()), Response::HTTP_FORBIDDEN);

        return $report;
    }

    /**
     * Busca no máximo $limit linhas. Pedimos UMA a mais só para saber se o
     * resultado foi cortado — sem precisar de um COUNT(*) extra no banco.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function limitedRows(Report $report, array $filters, int $limit): array
    {
        $rows = LazyCollection::make(fn () => yield from $report->rows($filters))
            ->take($limit + 1)
            ->values()
            ->all();

        $truncated = count($rows) > $limit;

        return [array_slice($rows, 0, $limit), $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(Report $report, array $filters, string $filename): Response
    {
        $columns = $report->columns();

        // Generator: as linhas são lidas do banco e escritas no arquivo aos
        // poucos, sem carregar o relatório inteiro na memória.
        $lines = (function () use ($report, $filters, $columns) {
            foreach ($report->rows($filters) as $row) {
                yield array_map(fn (string $key): string => $report->csvValue($columns[$key], $row[$key] ?? null), array_keys($columns));
            }

            $totals = $report->totals($filters);

            if ($totals !== []) {
                $firstKey = array_key_first($columns);

                yield array_map(fn (string $key): string => $key === $firstKey && ! array_key_exists($key, $totals)
                    ? (string) ($totals['_label'] ?? 'Total')
                    : $report->csvValue($columns[$key], $totals[$key] ?? null), array_keys($columns));
            }
        })();

        return CsvExporter::download($filename, array_column($columns, 'label'), $lines);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportPdf(Report $report, array $filters, string $filename): Response
    {
        [$rows, $truncated] = $this->limitedRows($report, $filters, self::PDF_ROW_LIMIT);

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'company' => $this->currentCompany->getOrFail(),
            'appliedFilters' => $report->describeFilters($filters),
            'rows' => $rows,
            'truncated' => $truncated,
            'rowLimit' => self::PDF_ROW_LIMIT,
            'totals' => $report->totals($filters),
            'summary' => $report->summary($filters),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    /**
     * Filtros preenchidos, prontos para a query string dos botões de exportação.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function queryString(array $filters): array
    {
        return array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
