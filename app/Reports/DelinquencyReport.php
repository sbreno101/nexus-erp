<?php

namespace App\Reports;

use App\Enums\BillStatus;
use App\Models\Customer;
use App\Models\Receivable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Inadimplência: valores VENCIDOS e não pagos por cliente, em faixas de atraso
 * ("aging"). É uma fotografia do dia de hoje, por isso não tem filtro de período.
 *
 * Dias de atraso = hoje − vencimento. No PostgreSQL, subtrair duas datas
 * (date − date) devolve diretamente o número de dias (inteiro).
 */
class DelinquencyReport extends Report
{
    public function key(): string
    {
        return 'inadimplencia';
    }

    public function title(): string
    {
        return 'Inadimplência';
    }

    public function description(): string
    {
        return 'Valores vencidos por cliente em faixas de atraso (1–30, 31–60, 61–90 e mais de 90 dias).';
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
        return 'exclamation-triangle';
    }

    public function filters(): array
    {
        return [
            'cliente' => ['type' => 'text', 'label' => 'Cliente (nome ou CPF/CNPJ)', 'default' => null],
        ];
    }

    public function columns(): array
    {
        return [
            'customer' => ['label' => 'Cliente', 'type' => 'text'],
            'document' => ['label' => 'CPF/CNPJ', 'type' => 'text'],
            'range_1_30' => ['label' => '1–30 dias', 'type' => 'money'],
            'range_31_60' => ['label' => '31–60 dias', 'type' => 'money'],
            'range_61_90' => ['label' => '61–90 dias', 'type' => 'money'],
            'range_90_plus' => ['label' => '+90 dias', 'type' => 'money'],
            'total_cents' => ['label' => 'Total vencido', 'type' => 'money'],
            'bills' => ['label' => 'Títulos', 'type' => 'number'],
            'oldest_due' => ['label' => 'Vencimento mais antigo', 'type' => 'date'],
            'max_days' => ['label' => 'Maior atraso (dias)', 'type' => 'number'],
        ];
    }

    public function rows(array $filters): iterable
    {
        $groups = $this->aggregateQuery($filters)
            ->addSelect('customer_id')
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->toBase()
            ->get();

        // Clientes carregados em UMA consulta (withTrashed: um cliente excluído
        // pode continuar devendo).
        $customers = Customer::withTrashed()
            ->whereIn('id', $groups->pluck('customer_id'))
            ->get(['id', 'name', 'trade_name', 'document'])
            ->keyBy('id');

        return $groups->map(function (object $group) use ($customers): array {
            $customer = $customers->get($group->customer_id);

            return [
                'customer' => $customer?->display_name ?? 'Cliente removido',
                'document' => $customer?->formatted_document,
                ...$this->metrics($group),
            ];
        })->all();
    }

    public function totals(array $filters): array
    {
        return ['_label' => 'Total', ...$this->overall($filters)];
    }

    public function summary(array $filters): array
    {
        $overall = $this->overall($filters);

        // Para dar contexto: quanto o vencido representa de todo o saldo a receber.
        $portfolio = (int) Receivable::query()
            ->whereIn('status', BillStatus::outstanding())
            ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0) AS total')
            ->value('total');

        $share = $portfolio > 0 ? number_format($overall['total_cents'] / $portfolio * 100, 1, ',', '.').'%' : '—';

        return [
            ['label' => 'Total vencido', 'value' => money($overall['total_cents']), 'icon' => 'exclamation-triangle', 'color' => 'red'],
            ['label' => 'Clientes inadimplentes', 'value' => (string) $overall['customers'], 'icon' => 'users', 'color' => 'yellow'],
            ['label' => 'Títulos vencidos', 'value' => (string) $overall['bills'], 'icon' => 'document-text', 'color' => 'brand'],
            ['label' => 'Vencido ÷ carteira', 'value' => $share, 'icon' => 'chart-bar', 'color' => 'purple', 'hint' => 'Carteira em aberto: '.money($portfolio)],
        ];
    }

    /**
     * Soma do saldo vencido por faixa de atraso; sem GROUP BY devolve o total geral.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Receivable>
     */
    private function aggregateQuery(array $filters): Builder
    {
        $today = Carbon::today()->toDateString();
        $daysLate = '(CAST(? AS date) - due_date)';
        $balance = '(amount_cents - paid_cents)';

        return Receivable::query()
            ->whereIn('status', BillStatus::outstanding())
            ->where('due_date', '<', $today)
            ->when($filters['cliente'] ?? null, fn (Builder $query, string $term) => $query->whereHas('customer', fn (Builder $customer) => $customer->search($term)))
            ->selectRaw("COALESCE(SUM({$balance}) FILTER (WHERE {$daysLate} BETWEEN 1 AND 30), 0) AS range_1_30", [$today])
            ->selectRaw("COALESCE(SUM({$balance}) FILTER (WHERE {$daysLate} BETWEEN 31 AND 60), 0) AS range_31_60", [$today])
            ->selectRaw("COALESCE(SUM({$balance}) FILTER (WHERE {$daysLate} BETWEEN 61 AND 90), 0) AS range_61_90", [$today])
            ->selectRaw("COALESCE(SUM({$balance}) FILTER (WHERE {$daysLate} > 90), 0) AS range_90_plus", [$today])
            ->selectRaw("COALESCE(SUM({$balance}), 0) AS total")
            ->selectRaw('COUNT(*) AS bills')
            ->selectRaw('COUNT(DISTINCT customer_id) AS customers')
            ->selectRaw('MIN(due_date) AS oldest_due')
            ->selectRaw("COALESCE(MAX({$daysLate}), 0) AS max_days", [$today]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{range_1_30: int, range_31_60: int, range_61_90: int, range_90_plus: int, total_cents: int, bills: int, oldest_due: string|null, max_days: int, customers: int}
     */
    private function overall(array $filters): array
    {
        return $this->remember('overall', $filters, function () use ($filters): array {
            $row = $this->aggregateQuery($filters)->toBase()->first();

            return [...$this->metrics($row), 'customers' => (int) $row->customers];
        });
    }

    /**
     * @return array{range_1_30: int, range_31_60: int, range_61_90: int, range_90_plus: int, total_cents: int, bills: int, oldest_due: string|null, max_days: int}
     */
    private function metrics(object $row): array
    {
        return [
            'range_1_30' => (int) $row->range_1_30,
            'range_31_60' => (int) $row->range_31_60,
            'range_61_90' => (int) $row->range_61_90,
            'range_90_plus' => (int) $row->range_90_plus,
            'total_cents' => (int) $row->total,
            'bills' => (int) $row->bills,
            'oldest_due' => $row->oldest_due,
            'max_days' => (int) $row->max_days,
        ];
    }
}
