<?php

namespace App\Reports;

use App\Enums\Priority;
use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ordens de serviço abertas no período, por responsável (assigned_to).
 *
 * - "Em andamento" soma as OS em execução e as pausadas (aguardando).
 * - "Atrasadas" = pendentes (aberta/em andamento/pausada) com prazo vencido.
 * - Tempo médio de conclusão = média de (completed_at − created_at) das
 *   concluídas, em horas. EXTRACT(EPOCH FROM intervalo) devolve segundos.
 * - Minutos registrados = soma de minutes_spent das atividades dessas OS,
 *   atribuídos ao responsável pela OS (não a quem lançou a atividade).
 */
class ServiceOrdersReport extends Report
{
    public function key(): string
    {
        return 'ordens-de-servico';
    }

    public function title(): string
    {
        return 'Ordens de serviço';
    }

    public function description(): string
    {
        return 'OS por responsável: situação, atrasos, tempo médio de conclusão e minutos registrados.';
    }

    public function group(): string
    {
        return 'Operacional';
    }

    public function permission(): string
    {
        return 'reports.operational';
    }

    public function icon(): string
    {
        return 'wrench';
    }

    public function filters(): array
    {
        return [
            'de' => ['type' => 'date', 'label' => 'Abertas de', 'default' => now()->startOfMonth()->toDateString()],
            'ate' => ['type' => 'date', 'label' => 'Abertas até', 'default' => now()->endOfMonth()->toDateString()],
            'prioridade' => ['type' => 'select', 'label' => 'Prioridade', 'options' => Priority::options(), 'placeholder' => 'Todas', 'default' => ''],
        ];
    }

    public function columns(): array
    {
        return [
            'assignee' => ['label' => 'Responsável', 'type' => 'text'],
            'total' => ['label' => 'Total no período', 'type' => 'number'],
            'open' => ['label' => 'Abertas', 'type' => 'number'],
            'in_progress' => ['label' => 'Em andamento', 'type' => 'number'],
            'completed' => ['label' => 'Concluídas', 'type' => 'number'],
            'cancelled' => ['label' => 'Canceladas', 'type' => 'number'],
            'overdue' => ['label' => 'Atrasadas', 'type' => 'number'],
            'avg_hours' => ['label' => 'Tempo médio (h)', 'type' => 'number', 'decimals' => 1],
            'minutes' => ['label' => 'Minutos registrados', 'type' => 'number'],
        ];
    }

    public function rows(array $filters): iterable
    {
        $groups = $this->aggregateQuery($filters)
            ->addSelect('service_orders.assigned_to')
            ->groupBy('service_orders.assigned_to')
            ->orderByDesc('total')
            ->toBase()
            ->get();

        $minutes = $this->minutesQuery($filters)
            ->addSelect('service_orders.assigned_to')
            ->groupBy('service_orders.assigned_to')
            ->toBase()
            ->pluck('minutes', 'assigned_to');

        $names = $this->userNames($groups->pluck('assigned_to'));

        return $groups->map(fn (object $group): array => [
            'assignee' => $group->assigned_to ? ($names[$group->assigned_to] ?? 'Usuário removido') : 'Sem responsável',
            ...$this->metrics($group, $this->minutesFor($minutes, $group->assigned_to)),
        ])->all();
    }

    public function totals(array $filters): array
    {
        return ['_label' => 'Total', ...$this->overall($filters)];
    }

    public function summary(array $filters): array
    {
        $overall = $this->overall($filters);

        return [
            ['label' => 'OS no período', 'value' => (string) $overall['total'], 'icon' => 'wrench', 'color' => 'brand', 'hint' => ($overall['open'] + $overall['in_progress']).' pendente(s)'],
            ['label' => 'Concluídas', 'value' => (string) $overall['completed'], 'icon' => 'check-circle', 'color' => 'green'],
            ['label' => 'Atrasadas', 'value' => (string) $overall['overdue'], 'icon' => 'exclamation-triangle', 'color' => 'red'],
            [
                'label' => 'Tempo médio de conclusão',
                'value' => $overall['avg_hours'] === null ? '—' : number_format($overall['avg_hours'], 1, ',', '.').' h',
                'icon' => 'clock',
                'color' => 'purple',
                'hint' => number_format($overall['minutes'] / 60, 1, ',', '.').' h registradas nas atividades',
            ],
        ];
    }

    /**
     * Consulta base (colunas qualificadas com "service_orders." porque a
     * consulta de minutos faz JOIN com as atividades, que também têm created_at).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ServiceOrder>
     */
    private function baseQuery(array $filters): Builder
    {
        [$from, $to] = $this->period($filters);

        return ServiceOrder::query()
            ->whereBetween('service_orders.created_at', [$from, $to])
            ->when($filters['prioridade'] ?? null, fn (Builder $query, string $priority) => $query->where('service_orders.priority', $priority));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ServiceOrder>
     */
    private function aggregateQuery(array $filters): Builder
    {
        $pending = self::sqlList(ServiceOrderStatus::pending());

        return $this->baseQuery($filters)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS open', [ServiceOrderStatus::Open->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status IN (?, ?)) AS in_progress', [ServiceOrderStatus::InProgress->value, ServiceOrderStatus::OnHold->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS completed', [ServiceOrderStatus::Completed->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS cancelled', [ServiceOrderStatus::Cancelled->value])
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ({$pending}) AND due_date < ?) AS overdue", [Carbon::today()->toDateString()])
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - created_at)) / 3600) FILTER (WHERE status = ? AND completed_at IS NOT NULL) AS avg_hours', [ServiceOrderStatus::Completed->value]);
    }

    /**
     * Minutos lançados nas atividades das OS filtradas.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ServiceOrder>
     */
    private function minutesQuery(array $filters): Builder
    {
        return $this->baseQuery($filters)
            ->join('service_order_activities', 'service_order_activities.service_order_id', '=', 'service_orders.id')
            ->selectRaw('COALESCE(SUM(service_order_activities.minutes_spent), 0) AS minutes');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: int, open: int, in_progress: int, completed: int, cancelled: int, overdue: int, avg_hours: float|null, minutes: int}
     */
    private function overall(array $filters): array
    {
        return $this->remember('overall', $filters, fn (): array => $this->metrics(
            $this->aggregateQuery($filters)->toBase()->first(),
            (int) $this->minutesQuery($filters)->value('minutes'),
        ));
    }

    /**
     * @param  Collection<int|string, mixed>  $minutes
     */
    private function minutesFor(Collection $minutes, int|string|null $assignedTo): int
    {
        // Responsável nulo vira chave "" no pluck().
        return (int) ($minutes[$assignedTo ?? ''] ?? 0);
    }

    /**
     * @return array{total: int, open: int, in_progress: int, completed: int, cancelled: int, overdue: int, avg_hours: float|null, minutes: int}
     */
    private function metrics(object $row, int $minutes): array
    {
        return [
            'total' => (int) $row->total,
            'open' => (int) $row->open,
            'in_progress' => (int) $row->in_progress,
            'completed' => (int) $row->completed,
            'cancelled' => (int) $row->cancelled,
            'overdue' => (int) $row->overdue,
            'avg_hours' => $row->avg_hours === null ? null : round((float) $row->avg_hours, 1),
            'minutes' => $minutes,
        ];
    }
}
