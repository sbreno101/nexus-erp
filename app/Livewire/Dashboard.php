<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Services\Dashboard\DashboardMetrics;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard gerencial com indicadores reais.
 *
 * Filtros: período (data inicial/final) e empresa. A opção "Todas as minhas
 * empresas" consolida os números das empresas em que o usuário possui a
 * permissão dashboard.view — validado no servidor a cada atualização.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Url(as: 'de')]
    public string $from = '';

    #[Url(as: 'ate')]
    public string $to = '';

    /**
     * "current" (empresa ativa), "all" (consolidado) ou o id de uma empresa.
     */
    #[Url(as: 'empresa')]
    public string $scope = 'current';

    public function mount(): void
    {
        $this->authorize('dashboard.view');

        $this->from = $this->from ?: now()->subMonths(5)->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->endOfMonth()->toDateString();
    }

    public function setPeriod(string $period): void
    {
        [$this->from, $this->to] = match ($period) {
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'quarter' => [now()->subMonths(2)->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default => [now()->subMonths(5)->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    public function render(): View
    {
        $user = Auth::user();
        $companyIds = $this->resolveCompanyIds($user);
        [$from, $to] = $this->resolvePeriod();

        $metrics = new DashboardMetrics($companyIds, $from, $to);
        $isSingleCompany = count($companyIds) === 1;

        return view('livewire.dashboard', [
            'kpis' => $metrics->kpis(),
            'revenueChart' => $metrics->monthlyRevenueVsExpenses(),
            'cashFlowChart' => $metrics->cashFlow(),
            'proposalsChart' => $metrics->proposalsByStatus(),
            'stagesChart' => $metrics->opportunitiesByStage(),
            'customersChart' => $metrics->customersByMonth(),
            'companies' => $this->companiesWithDashboardAccess($user),
            'myTasks' => $isSingleCompany ? Task::query()->pending()->where('assigned_to', $user->id)->orderBy('due_at')->limit(6)->get() : collect(),
            'urgentOrders' => $isSingleCompany && $user->can('viewAny', ServiceOrder::class)
                ? ServiceOrder::query()->pending()->with(['customer:id,name,trade_name', 'assignee:id,name'])
                    ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                    ->orderBy('due_date')->limit(6)->get()
                : collect(),
            'expiringContracts' => $isSingleCompany && $user->can('viewAny', Contract::class)
                ? Contract::query()->active()->whereNotNull('end_date')->whereDate('end_date', '<=', now()->addDays(60))
                    ->with('customer:id,name,trade_name')->orderBy('end_date')->limit(5)->get()
                : collect(),
            'periodLabel' => $from->format('d/m/Y').' a '.$to->format('d/m/Y'),
        ]);
    }

    /**
     * Resolve as empresas consideradas, SEMPRE limitadas às que o usuário acessa.
     *
     * @return list<int>
     */
    private function resolveCompanyIds(User $user): array
    {
        $allowed = $this->companiesWithDashboardAccess($user)->pluck('id')->all();
        $currentId = app(CurrentCompany::class)->id();

        return match (true) {
            $this->scope === 'all' => $allowed,
            ctype_digit($this->scope) && in_array((int) $this->scope, $allowed, true) => [(int) $this->scope],
            default => [$currentId],
        };
    }

    /**
     * @return Collection<int, Company>
     */
    private function companiesWithDashboardAccess(User $user): Collection
    {
        return $user->accessibleCompanies()
            ->filter(fn ($company) => $user->hasPermission('dashboard.view', $company->id))
            ->values();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(): array
    {
        try {
            $from = CarbonImmutable::parse($this->from)->startOfDay();
            $to = CarbonImmutable::parse($this->to)->endOfDay();
        } catch (\Throwable) {
            $from = now()->subMonths(5)->startOfMonth()->toImmutable();
            $to = now()->endOfMonth()->toImmutable();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        // Limita a 24 meses para manter os gráficos legíveis e as consultas leves.
        if ($from->diffInMonths($to) > 24) {
            $from = $to->subMonths(24)->startOfMonth();
        }

        return [$from, $to];
    }
}
