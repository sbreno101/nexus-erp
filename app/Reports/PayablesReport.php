<?php

namespace App\Reports;

use App\Enums\FinancialCategoryType;
use App\Models\CostCenter;
use App\Models\FinancialCategory;
use App\Models\Payable;
use App\Reports\Concerns\SummarizesBills;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contas a pagar por período de vencimento, com fornecedor, categoria e
 * centro de custo (útil para entender para onde vai o dinheiro).
 */
class PayablesReport extends Report
{
    use SummarizesBills;

    public function key(): string
    {
        return 'contas-a-pagar';
    }

    public function title(): string
    {
        return 'Contas a pagar';
    }

    public function description(): string
    {
        return 'Despesas por vencimento, com fornecedor, categoria, centro de custo e situação.';
    }

    public function group(): string
    {
        return 'Financeiro';
    }

    public function permission(): string
    {
        return 'reports.financial';
    }

    public function icon(): string
    {
        return 'arrow-up-tray';
    }

    public function filters(): array
    {
        return [
            'de' => ['type' => 'date', 'label' => 'Vencimento de', 'default' => now()->startOfMonth()->toDateString()],
            'ate' => ['type' => 'date', 'label' => 'Vencimento até', 'default' => now()->endOfMonth()->toDateString()],
            'situacao' => ['type' => 'select', 'label' => 'Situação', 'options' => $this->situationOptions(), 'default' => 'todos'],
            'categoria' => [
                'type' => 'select',
                'label' => 'Categoria',
                // Inclui categorias inativas: títulos antigos podem estar nelas.
                'options' => FinancialCategory::query()->where('type', FinancialCategoryType::Expense)->orderBy('name')->pluck('name', 'id')->all(),
                'placeholder' => 'Todas',
                'default' => '',
            ],
            'centro_custo' => [
                'type' => 'select',
                'label' => 'Centro de custo',
                'options' => CostCenter::query()->orderBy('code')->get(['id', 'code', 'name'])
                    ->mapWithKeys(fn (CostCenter $center): array => [$center->id => $center->code.' - '.$center->name])
                    ->all(),
                'placeholder' => 'Todos',
                'default' => '',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            'supplier' => ['label' => 'Fornecedor', 'type' => 'text'],
            'description' => ['label' => 'Descrição', 'type' => 'text'],
            'category' => ['label' => 'Categoria', 'type' => 'text'],
            'cost_center' => ['label' => 'Centro de custo', 'type' => 'text'],
            'competence' => ['label' => 'Competência', 'type' => 'text'],
            'due_date' => ['label' => 'Vencimento', 'type' => 'date'],
            'amount_cents' => ['label' => 'Valor', 'type' => 'money'],
            'paid_cents' => ['label' => 'Pago', 'type' => 'money'],
            'outstanding_cents' => ['label' => 'Em aberto', 'type' => 'money'],
            'status' => ['label' => 'Situação', 'type' => 'badge'],
        ];
    }

    public function rows(array $filters): iterable
    {
        $query = $this->baseQuery($filters)
            ->with(['supplier:id,name,trade_name', 'category:id,name', 'costCenter:id,code,name'])
            ->orderBy('due_date')
            ->orderBy('id');

        foreach ($query->lazy(500) as $payable) {
            yield [
                'supplier' => $payable->supplier?->display_name ?? 'Sem fornecedor',
                'description' => $payable->description,
                'category' => $payable->category?->name,
                'cost_center' => $payable->costCenter ? $payable->costCenter->code.' - '.$payable->costCenter->name : null,
                'competence' => $this->formatCompetence($payable->competence),
                'due_date' => $payable->due_date,
                'amount_cents' => $payable->amount_cents,
                'paid_cents' => $payable->paid_cents,
                'outstanding_cents' => $payable->outstandingCents(),
                'status' => ['label' => $payable->statusLabel(), 'color' => $payable->statusColor()],
            ];
        }
    }

    public function totals(array $filters): array
    {
        return $this->billTotalsRow($this->aggregates($filters));
    }

    public function summary(array $filters): array
    {
        return $this->billSummaryCards($this->aggregates($filters), 'Pago');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Payable>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Payable::query()
            ->whereBetween('due_date', [$filters['de'], $filters['ate']])
            ->when($filters['categoria'] ?? null, fn (Builder $query, string $category) => $query->where('financial_category_id', (int) $category))
            ->when($filters['centro_custo'] ?? null, fn (Builder $query, string $center) => $query->where('cost_center_id', (int) $center));

        return $this->applySituation($query, $filters['situacao'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{count: int, amount_cents: int, paid_cents: int, outstanding_cents: int, overdue_cents: int}
     */
    private function aggregates(array $filters): array
    {
        return $this->remember('aggregates', $filters, fn (): array => $this->billAggregates($this->baseQuery($filters)));
    }
}
