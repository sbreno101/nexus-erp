<?php

namespace App\Reports;

use App\Models\Receivable;
use App\Reports\Concerns\SummarizesBills;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contas a receber por período de vencimento.
 *
 * A coluna "Situação" usa statusLabel() do model, que devolve "Vencido"
 * para títulos em aberto/parciais com vencimento passado — a mesma regra
 * exibida no restante do sistema.
 */
class ReceivablesReport extends Report
{
    use SummarizesBills;

    public function key(): string
    {
        return 'contas-a-receber';
    }

    public function title(): string
    {
        return 'Contas a receber';
    }

    public function description(): string
    {
        return 'Títulos de clientes por vencimento, com valores pagos, em aberto e situação.';
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
        return 'arrow-down-tray';
    }

    public function filters(): array
    {
        return [
            'de' => ['type' => 'date', 'label' => 'Vencimento de', 'default' => now()->startOfMonth()->toDateString()],
            'ate' => ['type' => 'date', 'label' => 'Vencimento até', 'default' => now()->endOfMonth()->toDateString()],
            'situacao' => ['type' => 'select', 'label' => 'Situação', 'options' => $this->situationOptions(), 'default' => 'todos'],
            'cliente' => ['type' => 'text', 'label' => 'Cliente (nome ou CPF/CNPJ)', 'default' => null],
        ];
    }

    public function columns(): array
    {
        return [
            'customer' => ['label' => 'Cliente', 'type' => 'text'],
            'description' => ['label' => 'Descrição', 'type' => 'text'],
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
            ->with('customer:id,name,trade_name')
            ->orderBy('due_date')
            ->orderBy('id');

        foreach ($query->lazy(500) as $receivable) {
            yield [
                'customer' => $receivable->customer?->display_name,
                'description' => $receivable->description,
                'competence' => $this->formatCompetence($receivable->competence),
                'due_date' => $receivable->due_date,
                'amount_cents' => $receivable->amount_cents,
                'paid_cents' => $receivable->paid_cents,
                'outstanding_cents' => $receivable->outstandingCents(),
                'status' => ['label' => $receivable->statusLabel(), 'color' => $receivable->statusColor()],
            ];
        }
    }

    public function totals(array $filters): array
    {
        return $this->billTotalsRow($this->aggregates($filters));
    }

    public function summary(array $filters): array
    {
        return $this->billSummaryCards($this->aggregates($filters), 'Recebido');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Receivable>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Receivable::query()
            ->whereBetween('due_date', [$filters['de'], $filters['ate']])
            ->when($filters['cliente'] ?? null, fn (Builder $query, string $term) => $query->whereHas('customer', fn (Builder $customer) => $customer->search($term)));

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
