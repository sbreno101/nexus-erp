<?php

namespace App\Reports;

use App\Enums\BillStatus;
use App\Enums\ContractStatus;
use App\Enums\CustomerStatus;
use App\Enums\PersonType;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Receivable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Relatório de clientes: cadastro + indicadores financeiros por cliente.
 *
 * Os números de cada cliente (contratos ativos, recebido, em aberto) vêm de
 * SUBCONSULTAS na mesma query principal: o banco calcula tudo de uma vez,
 * em vez de rodar uma consulta extra para cada cliente (problema N+1).
 */
class CustomersReport extends Report
{
    public function key(): string
    {
        return 'clientes';
    }

    public function title(): string
    {
        return 'Clientes';
    }

    public function description(): string
    {
        return 'Carteira de clientes com contratos ativos, total recebido e saldo em aberto.';
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
        return 'users';
    }

    public function filters(): array
    {
        return [
            'status' => ['type' => 'select', 'label' => 'Status', 'options' => CustomerStatus::options(), 'placeholder' => 'Todos', 'default' => ''],
            'tipo' => ['type' => 'select', 'label' => 'Tipo', 'options' => PersonType::options(), 'placeholder' => 'PF e PJ', 'default' => ''],
            // Sem período padrão: por padrão o relatório mostra a carteira inteira.
            'de' => ['type' => 'date', 'label' => 'Cadastrado de', 'default' => null],
            'ate' => ['type' => 'date', 'label' => 'Cadastrado até', 'default' => null],
        ];
    }

    public function columns(): array
    {
        return [
            'name' => ['label' => 'Cliente', 'type' => 'text'],
            'type' => ['label' => 'Tipo', 'type' => 'text'],
            'document' => ['label' => 'CPF/CNPJ', 'type' => 'text'],
            'status' => ['label' => 'Status', 'type' => 'badge'],
            'owner' => ['label' => 'Responsável', 'type' => 'text'],
            'created_at' => ['label' => 'Cadastro', 'type' => 'date'],
            'active_contracts' => ['label' => 'Contratos ativos', 'type' => 'number'],
            'received_cents' => ['label' => 'Total recebido', 'type' => 'money'],
            'outstanding_cents' => ['label' => 'Em aberto', 'type' => 'money'],
        ];
    }

    public function rows(array $filters): iterable
    {
        $query = $this->baseQuery($filters)
            ->with('owner:id,name')
            ->select('customers.*')
            ->addSelect([
                'active_contracts' => Contract::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('contracts.customer_id', 'customers.id')
                    ->where('status', ContractStatus::Active),
                'received_cents' => Receivable::query()
                    ->selectRaw('COALESCE(SUM(paid_cents), 0)')
                    ->whereColumn('receivables.customer_id', 'customers.id'),
                'outstanding_cents' => Receivable::query()
                    ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0)')
                    ->whereColumn('receivables.customer_id', 'customers.id')
                    ->whereIn('status', BillStatus::outstanding()),
            ])
            // "id" como desempate: lazy() pagina por OFFSET e precisa de ordem estável.
            ->orderBy('name')
            ->orderBy('id');

        foreach ($query->lazy(500) as $customer) {
            yield [
                'name' => $customer->display_name,
                'type' => $customer->type->label(),
                'document' => $customer->formatted_document,
                'status' => ['label' => $customer->status->label(), 'color' => $customer->status->color()],
                'owner' => $customer->owner?->name,
                'created_at' => $customer->created_at,
                'active_contracts' => (int) $customer->active_contracts,
                'received_cents' => (int) $customer->received_cents,
                'outstanding_cents' => (int) $customer->outstanding_cents,
            ];
        }
    }

    public function totals(array $filters): array
    {
        $totals = $this->aggregates($filters);

        return [
            'name' => $totals['customers'].' cliente(s)',
            'active_contracts' => $totals['active_contracts'],
            'received_cents' => $totals['received_cents'],
            'outstanding_cents' => $totals['outstanding_cents'],
        ];
    }

    public function summary(array $filters): array
    {
        $totals = $this->aggregates($filters);

        return [
            ['label' => 'Clientes', 'value' => (string) $totals['customers'], 'icon' => 'users', 'color' => 'brand', 'hint' => $totals['active_customers'].' ativo(s)'],
            ['label' => 'Contratos ativos', 'value' => (string) $totals['active_contracts'], 'icon' => 'document-check', 'color' => 'purple'],
            ['label' => 'Total recebido', 'value' => money($totals['received_cents']), 'icon' => 'check-circle', 'color' => 'green'],
            ['label' => 'Em aberto', 'value' => money($totals['outstanding_cents']), 'icon' => 'banknotes', 'color' => 'blue'],
        ];
    }

    /**
     * Consulta base com os filtros aplicados (reaproveitada por linhas e totais).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Customer>
     */
    private function baseQuery(array $filters): Builder
    {
        [$from, $to] = $this->period($filters);

        return Customer::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['tipo'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($from, fn (Builder $query) => $query->where('customers.created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('customers.created_at', '<=', $to));
    }

    /**
     * Totais calculados no banco (4 consultas agregadas, independente da quantidade de clientes).
     *
     * @param  array<string, mixed>  $filters
     * @return array{customers: int, active_customers: int, active_contracts: int, received_cents: int, outstanding_cents: int}
     */
    private function aggregates(array $filters): array
    {
        return $this->remember('aggregates', $filters, fn (): array => $this->calculateAggregates($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{customers: int, active_customers: int, active_contracts: int, received_cents: int, outstanding_cents: int}
     */
    private function calculateAggregates(array $filters): array
    {
        $customers = $this->baseQuery($filters)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS active', [CustomerStatus::Active->value])
            ->toBase()
            ->first();

        $customerIds = $this->baseQuery($filters)->select('customers.id');

        $receivables = Receivable::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('COALESCE(SUM(paid_cents), 0) AS received')
            ->selectRaw('COALESCE(SUM(amount_cents - paid_cents) FILTER (WHERE status IN ('.self::sqlList(BillStatus::outstanding()).')), 0) AS outstanding')
            ->toBase()
            ->first();

        return [
            'customers' => (int) $customers->total,
            'active_customers' => (int) $customers->active,
            'active_contracts' => Contract::query()->whereIn('customer_id', $customerIds)->where('status', ContractStatus::Active)->count(),
            'received_cents' => (int) $receivables->received,
            'outstanding_cents' => (int) $receivables->outstanding,
        ];
    }
}
