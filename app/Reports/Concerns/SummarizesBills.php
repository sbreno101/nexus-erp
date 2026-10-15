<?php

namespace App\Reports\Concerns;

use App\Enums\BillStatus;
use App\Models\Payable;
use App\Models\Receivable;
use App\Reports\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Regras comuns aos relatórios de títulos (contas a receber e a pagar):
 * filtro de situação, linha de totais e cartões de resumo.
 *
 * Fica num trait porque Receivable e Payable têm exatamente as mesmas
 * colunas financeiras (veja o trait IsBill dos models).
 *
 * @mixin Report
 */
trait SummarizesBills
{
    /**
     * Opções do filtro "Situação".
     *
     * @return array<string, string>
     */
    protected function situationOptions(): array
    {
        return [
            'todos' => 'Todas (exceto canceladas)',
            'abertos' => 'Em aberto (a vencer e vencidos)',
            'vencidos' => 'Somente vencidos',
            'quitados' => 'Quitados',
            'cancelados' => 'Cancelados',
        ];
    }

    /**
     * Aplica o filtro de situação. "Vencido" não é um status gravado: é
     * derivado (em aberto/parcial com vencimento anterior a hoje).
     *
     * @template TModel of Receivable|Payable
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySituation(Builder $query, ?string $situation): Builder
    {
        return match ($situation) {
            'abertos' => $query->whereIn('status', BillStatus::outstanding()),
            'vencidos' => $query->whereIn('status', BillStatus::outstanding())->whereDate('due_date', '<', Carbon::today()),
            'quitados' => $query->where('status', BillStatus::Paid),
            'cancelados' => $query->where('status', BillStatus::Cancelled),
            default => $query->where('status', '!=', BillStatus::Cancelled),
        };
    }

    /**
     * Somatórios dos títulos filtrados, calculados no banco.
     * Título cancelado não tem saldo em aberto (mesma regra de outstandingCents()).
     *
     * @template TModel of Receivable|Payable
     *
     * @param  Builder<TModel>  $query
     * @return array{count: int, amount_cents: int, paid_cents: int, outstanding_cents: int, overdue_cents: int}
     */
    protected function billAggregates(Builder $query): array
    {
        $outstanding = self::sqlList(BillStatus::outstanding());

        $row = $query
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) AS amount')
            ->selectRaw('COALESCE(SUM(paid_cents), 0) AS paid')
            ->selectRaw("COALESCE(SUM(amount_cents - paid_cents) FILTER (WHERE status IN ({$outstanding})), 0) AS outstanding")
            ->selectRaw("COALESCE(SUM(amount_cents - paid_cents) FILTER (WHERE status IN ({$outstanding}) AND due_date < ?), 0) AS overdue", [Carbon::today()->toDateString()])
            ->toBase()
            ->first();

        return [
            'count' => (int) $row->total,
            'amount_cents' => (int) $row->amount,
            'paid_cents' => (int) $row->paid,
            'outstanding_cents' => (int) $row->outstanding,
            'overdue_cents' => (int) $row->overdue,
        ];
    }

    /**
     * @param  array{count: int, amount_cents: int, paid_cents: int, outstanding_cents: int, overdue_cents: int}  $totals
     * @return array<string, mixed>
     */
    protected function billTotalsRow(array $totals): array
    {
        return [
            '_label' => 'Total ('.$totals['count'].' título(s))',
            'amount_cents' => $totals['amount_cents'],
            'paid_cents' => $totals['paid_cents'],
            'outstanding_cents' => $totals['outstanding_cents'],
        ];
    }

    /**
     * @param  array{count: int, amount_cents: int, paid_cents: int, outstanding_cents: int, overdue_cents: int}  $totals
     * @return list<array{label: string, value: string, icon: string, color: string, hint?: string}>
     */
    protected function billSummaryCards(array $totals, string $paidLabel): array
    {
        return [
            ['label' => 'Valor dos títulos', 'value' => money($totals['amount_cents']), 'icon' => 'document-text', 'color' => 'brand', 'hint' => $totals['count'].' título(s)'],
            ['label' => $paidLabel, 'value' => money($totals['paid_cents']), 'icon' => 'check-circle', 'color' => 'green'],
            ['label' => 'Em aberto', 'value' => money($totals['outstanding_cents']), 'icon' => 'banknotes', 'color' => 'blue'],
            ['label' => 'Vencido', 'value' => money($totals['overdue_cents']), 'icon' => 'exclamation-triangle', 'color' => 'red'],
        ];
    }

    /**
     * Competência exibida como mês/ano (ex.: "10/2026").
     */
    protected function formatCompetence(?Carbon $competence): ?string
    {
        return $competence?->format('m/Y');
    }
}
