<?php

namespace App\Services\Dashboard;

use App\Enums\BillStatus;
use App\Enums\ContractStatus;
use App\Enums\CustomerStatus;
use App\Enums\EntryStatus;
use App\Enums\OpportunityStatus;
use App\Enums\ProposalStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\TicketStatus;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Opportunity;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PipelineStage;
use App\Models\Proposal;
use App\Models\Receivable;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Calcula os indicadores do dashboard a partir do banco de dados.
 *
 * Recebe EXPLICITAMENTE a lista de empresas (permite o painel consolidado
 * de várias empresas). Quem chama é responsável por garantir que o usuário
 * tem acesso a todas elas (veja App\Livewire\Dashboard).
 *
 * As fórmulas de cada indicador estão documentadas em docs/06-regras-de-negocio.md.
 */
class DashboardMetrics
{
    /**
     * @param  list<int>  $companyIds
     */
    public function __construct(
        private array $companyIds,
        private CarbonImmutable $from,
        private CarbonImmutable $to,
    ) {}

    /**
     * @return array<string, int|float|null>
     */
    public function kpis(): array
    {
        $proposalsDecided = Proposal::query()->forCompanies($this->companyIds)
            ->whereIn('status', [ProposalStatus::Approved, ProposalStatus::Rejected, ProposalStatus::Expired])
            ->whereRaw('COALESCE(approved_at, rejected_at, updated_at) BETWEEN ? AND ?', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->selectRaw("COUNT(*) AS total, COUNT(*) FILTER (WHERE status = 'approved') AS approved")
            ->first();

        $decided = (int) $proposalsDecided?->total;

        return [
            // Clientes com status "Ativo".
            'active_customers' => Customer::query()->forCompanies($this->companyIds)->where('status', CustomerStatus::Active)->count(),
            // Clientes cadastrados no período.
            'new_customers' => Customer::query()->forCompanies($this->companyIds)->whereBetween('created_at', [$this->from->startOfDay(), $this->to->endOfDay()])->count(),
            'active_contracts' => Contract::query()->forCompanies($this->companyIds)->where('status', ContractStatus::Active)->count(),
            // Receita recorrente mensal (MRR) estimada dos contratos ativos.
            'monthly_recurring_cents' => $this->monthlyRecurringRevenue(),
            'open_proposals' => Proposal::query()->forCompanies($this->companyIds)->whereIn('status', ProposalStatus::open())->count(),
            'open_proposals_cents' => (int) Proposal::query()->forCompanies($this->companyIds)->whereIn('status', ProposalStatus::open())->sum('total_cents'),
            // Conversão = aprovadas / (aprovadas + recusadas + expiradas) decididas no período.
            'conversion_rate' => $decided > 0 ? round(((int) $proposalsDecided->approved / $decided) * 100, 1) : null,
            // Receita recebida = recebimentos confirmados (não estornados) no período.
            'revenue_received_cents' => $this->confirmedPayments(incoming: true),
            'expenses_paid_cents' => $this->confirmedPayments(incoming: false),
            // Saldo consolidado das contas financeiras ativas.
            'balance_cents' => (int) FinancialAccount::query()->forCompanies($this->companyIds)->where('is_active', true)->sum('current_balance_cents'),
            'overdue_receivables_cents' => $this->outstandingSum(Receivable::class, overdue: true),
            'overdue_receivables_count' => Receivable::query()->forCompanies($this->companyIds)->overdue()->count(),
            'upcoming_receivables_cents' => $this->outstandingSum(Receivable::class, overdue: false, nextDays: 30),
            'overdue_payables_cents' => $this->outstandingSum(Payable::class, overdue: true),
            'upcoming_payables_cents' => $this->outstandingSum(Payable::class, overdue: false, nextDays: 30),
            'open_tickets' => Ticket::query()->forCompanies($this->companyIds)->whereIn('status', TicketStatus::open())->count(),
            'pending_service_orders' => ServiceOrder::query()->forCompanies($this->companyIds)->whereIn('status', ServiceOrderStatus::pending())->count(),
            'overdue_service_orders' => ServiceOrder::query()->forCompanies($this->companyIds)->whereIn('status', ServiceOrderStatus::pending())->whereDate('due_date', '<', today())->count(),
            'open_pipeline_cents' => (int) Opportunity::query()->forCompanies($this->companyIds)->where('status', OpportunityStatus::Open)->sum('estimated_value_cents'),
        ];
    }

    /**
     * Receitas x despesas realizadas por mês (recebimentos e pagamentos confirmados).
     *
     * @return array{labels: list<string>, income: list<int>, expense: list<int>}
     */
    public function monthlyRevenueVsExpenses(): array
    {
        $rows = Payment::query()->forCompanies($this->companyIds)
            ->where('status', EntryStatus::Confirmed)
            ->whereBetween('paid_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw("to_char(paid_on, 'YYYY-MM') AS month")
            ->selectRaw('SUM(CASE WHEN receivable_id IS NOT NULL THEN amount_cents ELSE 0 END) AS income')
            ->selectRaw('SUM(CASE WHEN payable_id IS NOT NULL THEN amount_cents ELSE 0 END) AS expense')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $result = ['labels' => [], 'income' => [], 'expense' => []];

        foreach ($this->months() as $month) {
            $key = $month->format('Y-m');
            $result['labels'][] = $month->translatedFormat('M/y');
            $result['income'][] = (int) ($rows[$key]->income ?? 0);
            $result['expense'][] = (int) ($rows[$key]->expense ?? 0);
        }

        return $result;
    }

    /**
     * Fluxo de caixa realizado: entradas, saídas e saldo acumulado por mês
     * (com base no extrato das contas, incluindo lançamentos avulsos e estornos;
     * transferências internas são desconsideradas pois não alteram o saldo total).
     *
     * @return array{labels: list<string>, inflow: list<int>, outflow: list<int>, balance: list<int>}
     */
    public function cashFlow(): array
    {
        // transfer_id também está nos estornos de transferência, que igualmente não alteram o total.
        $base = FinancialTransaction::query()->forCompanies($this->companyIds)->whereNull('transfer_id');

        $opening = (int) FinancialAccount::query()->forCompanies($this->companyIds)->sum('opening_balance_cents')
            + (int) (clone $base)->where('occurred_on', '<', $this->from->toDateString())
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE -amount_cents END), 0) AS total")
                ->value('total');

        $rows = (clone $base)
            ->whereBetween('occurred_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw("to_char(occurred_on, 'YYYY-MM') AS month")
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE 0 END) AS inflow")
            ->selectRaw("SUM(CASE WHEN direction = 'out' THEN amount_cents ELSE 0 END) AS outflow")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $result = ['labels' => [], 'inflow' => [], 'outflow' => [], 'balance' => []];
        $running = $opening;

        foreach ($this->months() as $month) {
            $key = $month->format('Y-m');
            $inflow = (int) ($rows[$key]->inflow ?? 0);
            $outflow = (int) ($rows[$key]->outflow ?? 0);
            $running += $inflow - $outflow;

            $result['labels'][] = $month->translatedFormat('M/y');
            $result['inflow'][] = $inflow;
            $result['outflow'][] = $outflow;
            $result['balance'][] = $running;
        }

        return $result;
    }

    /**
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    public function proposalsByStatus(): array
    {
        $counts = Proposal::query()->forCompanies($this->companyIds)
            ->whereBetween('issue_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $palette = ['gray' => '#94a3b8', 'blue' => '#3b82f6', 'green' => '#10b981', 'red' => '#ef4444', 'yellow' => '#f59e0b'];
        $result = ['labels' => [], 'values' => [], 'colors' => []];

        foreach (ProposalStatus::cases() as $status) {
            $result['labels'][] = $status->label();
            $result['values'][] = (int) ($counts[$status->value] ?? 0);
            $result['colors'][] = $palette[$status->color()] ?? '#94a3b8';
        }

        return $result;
    }

    /**
     * Oportunidades abertas por etapa do funil (quantidade e valor).
     *
     * @return array{labels: list<string>, counts: list<int>, values: list<int>, colors: list<string>}
     */
    public function opportunitiesByStage(): array
    {
        $stages = PipelineStage::query()->forCompanies($this->companyIds)
            ->where('is_won', false)->where('is_lost', false)
            ->withCount(['opportunities as open_count' => fn ($query) => $query->where('status', OpportunityStatus::Open)])
            ->withSum(['opportunities as open_value' => fn ($query) => $query->where('status', OpportunityStatus::Open)], 'estimated_value_cents')
            ->orderBy('position')
            ->get()
            ->groupBy('name');

        $result = ['labels' => [], 'counts' => [], 'values' => [], 'colors' => []];

        foreach ($stages as $name => $group) {
            $result['labels'][] = $name;
            $result['counts'][] = (int) $group->sum('open_count');
            $result['values'][] = (int) $group->sum('open_value');
            $result['colors'][] = $group->first()->color;
        }

        return $result;
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function customersByMonth(): array
    {
        $rows = Customer::query()->forCompanies($this->companyIds)
            ->whereBetween('created_at', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->selectRaw("to_char(created_at, 'YYYY-MM') AS month, COUNT(*) AS total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $result = ['labels' => [], 'values' => []];

        foreach ($this->months() as $month) {
            $result['labels'][] = $month->translatedFormat('M/y');
            $result['values'][] = (int) ($rows[$month->format('Y-m')] ?? 0);
        }

        return $result;
    }

    private function monthlyRecurringRevenue(): int
    {
        return (int) Contract::query()->forCompanies($this->companyIds)
            ->where('status', ContractStatus::Active)
            ->selectRaw("COALESCE(SUM(amount_cents / CASE billing_frequency WHEN 'quarterly' THEN 3 WHEN 'semiannual' THEN 6 WHEN 'annual' THEN 12 ELSE 1 END), 0) AS total")
            ->value('total');
    }

    private function confirmedPayments(bool $incoming): int
    {
        return (int) Payment::query()->forCompanies($this->companyIds)
            ->where('status', EntryStatus::Confirmed)
            ->whereNotNull($incoming ? 'receivable_id' : 'payable_id')
            ->whereBetween('paid_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->sum('amount_cents');
    }

    /**
     * @param  class-string<Receivable|Payable>  $model
     */
    private function outstandingSum(string $model, bool $overdue, int $nextDays = 0): int
    {
        $query = $model::query()->forCompanies($this->companyIds)->whereIn('status', BillStatus::outstanding());

        $overdue
            ? $query->whereDate('due_date', '<', today())
            : $query->whereBetween('due_date', [today()->toDateString(), today()->addDays($nextDays)->toDateString()]);

        return (int) $query->sum(DB::raw('amount_cents - paid_cents'));
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function months(): array
    {
        $months = [];

        foreach (CarbonPeriod::create($this->from->startOfMonth(), '1 month', $this->to->startOfMonth()) as $month) {
            $months[] = CarbonImmutable::instance($month);
        }

        return $months;
    }
}
