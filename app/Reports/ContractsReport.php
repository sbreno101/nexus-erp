<?php

namespace App\Reports;

use App\Enums\BillingFrequency;
use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Carteira de contratos com o valor mensal equivalente (MRR).
 *
 * MRR ("Monthly Recurring Revenue") = valor periódico ÷ meses do período de
 * cobrança. Ex.: contrato anual de R$ 12.000 equivale a R$ 1.000/mês.
 * A mesma fórmula (divisão inteira em centavos) é usada no dashboard.
 */
class ContractsReport extends Report
{
    /**
     * Expressão SQL do MRR de cada contrato, em centavos.
     */
    private const string MRR_SQL = "amount_cents / CASE billing_frequency WHEN 'quarterly' THEN 3 WHEN 'semiannual' THEN 6 WHEN 'annual' THEN 12 ELSE 1 END";

    public function key(): string
    {
        return 'contratos';
    }

    public function title(): string
    {
        return 'Contratos';
    }

    public function description(): string
    {
        return 'Contratos por status e periodicidade, com valor mensal equivalente (MRR).';
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
        return 'document-check';
    }

    public function filters(): array
    {
        return [
            'status' => ['type' => 'select', 'label' => 'Status', 'options' => ContractStatus::options(), 'placeholder' => 'Todos', 'default' => ''],
            'periodicidade' => ['type' => 'select', 'label' => 'Periodicidade', 'options' => BillingFrequency::options(), 'placeholder' => 'Todas', 'default' => ''],
        ];
    }

    public function columns(): array
    {
        return [
            'number' => ['label' => 'Número', 'type' => 'text'],
            'customer' => ['label' => 'Cliente', 'type' => 'text'],
            'status' => ['label' => 'Status', 'type' => 'badge'],
            'frequency' => ['label' => 'Periodicidade', 'type' => 'text'],
            'amount_cents' => ['label' => 'Valor periódico', 'type' => 'money'],
            'mrr_cents' => ['label' => 'Valor mensal (MRR)', 'type' => 'money'],
            'start_date' => ['label' => 'Início', 'type' => 'date'],
            'end_date' => ['label' => 'Término', 'type' => 'date'],
            'auto_renew' => ['label' => 'Renovação automática', 'type' => 'text'],
        ];
    }

    public function rows(array $filters): iterable
    {
        $query = $this->baseQuery($filters)
            ->with('customer:id,name,trade_name')
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        foreach ($query->lazy(500) as $contract) {
            yield [
                'number' => $contract->number,
                'customer' => $contract->customer?->display_name,
                'status' => ['label' => $contract->status->label(), 'color' => $contract->status->color()],
                'frequency' => $contract->billing_frequency->label(),
                'amount_cents' => $contract->amount_cents,
                'mrr_cents' => intdiv($contract->amount_cents, $contract->billing_frequency->months()),
                'start_date' => $contract->start_date,
                'end_date' => $contract->end_date,
                'auto_renew' => $contract->auto_renew ? 'Sim' : 'Não',
            ];
        }
    }

    public function totals(array $filters): array
    {
        return [
            '_label' => 'MRR dos contratos ativos',
            'mrr_cents' => $this->aggregates($filters)['active_mrr_cents'],
        ];
    }

    public function summary(array $filters): array
    {
        $totals = $this->aggregates($filters);

        return [
            ['label' => 'Contratos listados', 'value' => (string) $totals['contracts'], 'icon' => 'document-text', 'color' => 'brand'],
            ['label' => 'Contratos ativos', 'value' => (string) $totals['active'], 'icon' => 'document-check', 'color' => 'green'],
            ['label' => 'MRR (ativos)', 'value' => money($totals['active_mrr_cents']), 'icon' => 'banknotes', 'color' => 'blue', 'hint' => 'Receita recorrente mensal'],
            ['label' => 'Receita anualizada', 'value' => money($totals['active_mrr_cents'] * 12), 'icon' => 'arrow-trending-up', 'color' => 'purple', 'hint' => 'MRR × 12'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Contract>
     */
    private function baseQuery(array $filters): Builder
    {
        return Contract::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['periodicidade'] ?? null, fn (Builder $query, string $frequency) => $query->where('billing_frequency', $frequency));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{contracts: int, active: int, active_mrr_cents: int}
     */
    private function aggregates(array $filters): array
    {
        return $this->remember('aggregates', $filters, function () use ($filters): array {
            $row = $this->baseQuery($filters)
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS active', [ContractStatus::Active->value])
                ->selectRaw('COALESCE(SUM('.self::MRR_SQL.') FILTER (WHERE status = ?), 0) AS active_mrr', [ContractStatus::Active->value])
                ->toBase()
                ->first();

            return [
                'contracts' => (int) $row->total,
                'active' => (int) $row->active,
                'active_mrr_cents' => (int) $row->active_mrr,
            ];
        });
    }
}
