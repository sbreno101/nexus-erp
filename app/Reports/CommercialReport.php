<?php

namespace App\Reports;

use App\Enums\OpportunityStatus;
use App\Enums\ProposalStatus;
use App\Models\Opportunity;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Desempenho comercial: propostas emitidas no período, agrupadas por responsável.
 *
 * Uma única consulta agregada (GROUP BY owner_id) com "COUNT(*) FILTER (WHERE ...)"
 * — recurso do PostgreSQL que conta, na mesma passada, só as linhas que
 * atendem a cada condição (aprovadas, recusadas...).
 *
 * Taxa de conversão = aprovadas ÷ decididas (aprovadas + recusadas + expiradas).
 * Propostas em aberto ou canceladas não entram na conta, pois ainda não
 * tiveram (ou não terão) uma decisão do cliente.
 */
class CommercialReport extends Report
{
    public function key(): string
    {
        return 'comercial';
    }

    public function title(): string
    {
        return 'Desempenho comercial';
    }

    public function description(): string
    {
        return 'Propostas emitidas por responsável, aprovações, valor aprovado e taxa de conversão.';
    }

    public function group(): string
    {
        return 'Comercial';
    }

    public function permission(): string
    {
        return 'reports.commercial';
    }

    public function icon(): string
    {
        return 'presentation-chart';
    }

    public function filters(): array
    {
        return [
            'de' => ['type' => 'date', 'label' => 'Emitidas de', 'default' => now()->startOfMonth()->toDateString()],
            'ate' => ['type' => 'date', 'label' => 'Emitidas até', 'default' => now()->endOfMonth()->toDateString()],
        ];
    }

    public function columns(): array
    {
        return [
            'owner' => ['label' => 'Responsável', 'type' => 'text'],
            'issued' => ['label' => 'Emitidas', 'type' => 'number'],
            'approved' => ['label' => 'Aprovadas', 'type' => 'number'],
            'rejected' => ['label' => 'Recusadas', 'type' => 'number'],
            'expired' => ['label' => 'Expiradas', 'type' => 'number'],
            'open' => ['label' => 'Em aberto', 'type' => 'number'],
            'approved_cents' => ['label' => 'Valor aprovado', 'type' => 'money'],
            'conversion' => ['label' => 'Conversão', 'type' => 'percent'],
        ];
    }

    public function rows(array $filters): iterable
    {
        $groups = $this->aggregateQuery($filters)
            ->addSelect('owner_id')
            ->groupBy('owner_id')
            ->orderByDesc('approved_cents')
            ->toBase()
            ->get();

        $names = $this->userNames($groups->pluck('owner_id'));

        return $groups->map(fn (object $group): array => [
            'owner' => $group->owner_id ? ($names[$group->owner_id] ?? 'Usuário removido') : 'Sem responsável',
            ...$this->metrics($group),
        ])->all();
    }

    public function totals(array $filters): array
    {
        return ['_label' => 'Total', ...$this->overall($filters)];
    }

    public function summary(array $filters): array
    {
        $overall = $this->overall($filters);

        $pipeline = Opportunity::query()
            ->where('status', OpportunityStatus::Open)
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(estimated_value_cents), 0) AS value')
            ->toBase()
            ->first();

        return [
            ['label' => 'Propostas emitidas', 'value' => (string) $overall['issued'], 'icon' => 'document-text', 'color' => 'brand', 'hint' => $overall['open'].' em aberto'],
            ['label' => 'Valor aprovado', 'value' => money($overall['approved_cents']), 'icon' => 'check-circle', 'color' => 'green', 'hint' => $overall['approved'].' proposta(s) aprovada(s)'],
            [
                'label' => 'Taxa de conversão',
                'value' => $overall['conversion'] === null ? '—' : number_format($overall['conversion'], 1, ',', '.').'%',
                'icon' => 'arrow-trending-up',
                'color' => 'purple',
                'hint' => 'Aprovadas ÷ decididas no período',
            ],
            // O funil é uma "fotografia" atual (não depende do período filtrado).
            ['label' => 'Funil em aberto', 'value' => money((int) $pipeline->value), 'icon' => 'funnel', 'color' => 'yellow', 'hint' => (int) $pipeline->total.' oportunidade(s) abertas hoje'],
        ];
    }

    /**
     * Consulta com as contagens/somas por status; sem GROUP BY devolve o total geral.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Proposal>
     */
    private function aggregateQuery(array $filters): Builder
    {
        return Proposal::query()
            ->whereBetween('issue_date', [$filters['de'], $filters['ate']])
            ->selectRaw('COUNT(*) AS issued')
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS approved', [ProposalStatus::Approved->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS rejected', [ProposalStatus::Rejected->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS expired', [ProposalStatus::Expired->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status IN ('.self::sqlList(ProposalStatus::open()).')) AS open')
            ->selectRaw('COALESCE(SUM(total_cents) FILTER (WHERE status = ?), 0) AS approved_cents', [ProposalStatus::Approved->value]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{issued: int, approved: int, rejected: int, expired: int, open: int, approved_cents: int, conversion: float|null}
     */
    private function overall(array $filters): array
    {
        return $this->remember('overall', $filters, fn (): array => $this->metrics($this->aggregateQuery($filters)->toBase()->first()));
    }

    /**
     * Converte a linha agregada do banco em métricas tipadas + taxa de conversão.
     *
     * @return array{issued: int, approved: int, rejected: int, expired: int, open: int, approved_cents: int, conversion: float|null}
     */
    private function metrics(object $row): array
    {
        $approved = (int) $row->approved;
        $decided = $approved + (int) $row->rejected + (int) $row->expired;

        return [
            'issued' => (int) $row->issued,
            'approved' => $approved,
            'rejected' => (int) $row->rejected,
            'expired' => (int) $row->expired,
            'open' => (int) $row->open,
            'approved_cents' => (int) $row->approved_cents,
            'conversion' => $decided > 0 ? round($approved / $decided * 100, 1) : null,
        ];
    }
}
