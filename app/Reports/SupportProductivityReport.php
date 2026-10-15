<?php

namespace App\Reports;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Produtividade do atendimento: chamados recebidos no período por atendente.
 *
 * - Resolvidos = status "Resolvido" ou "Encerrado".
 * - Em aberto = aberto, em atendimento ou aguardando cliente.
 * - Primeira resposta (h) = média de (first_response_at − created_at).
 * - Resolução (h) = média de (resolved_at, ou closed_at se não houver, − created_at)
 *   dos chamados resolvidos/encerrados.
 */
class SupportProductivityReport extends Report
{
    public function key(): string
    {
        return 'produtividade-atendimento';
    }

    public function title(): string
    {
        return 'Produtividade do atendimento';
    }

    public function description(): string
    {
        return 'Chamados por atendente: recebidos, resolvidos, em aberto, tempo de primeira resposta e de resolução.';
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
        return 'lifebuoy';
    }

    public function filters(): array
    {
        return [
            'de' => ['type' => 'date', 'label' => 'Recebidos de', 'default' => now()->startOfMonth()->toDateString()],
            'ate' => ['type' => 'date', 'label' => 'Recebidos até', 'default' => now()->endOfMonth()->toDateString()],
            'categoria' => [
                'type' => 'select',
                'label' => 'Categoria',
                'options' => TicketCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
                'placeholder' => 'Todas',
                'default' => '',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            'assignee' => ['label' => 'Atendente', 'type' => 'text'],
            'received' => ['label' => 'Recebidos', 'type' => 'number'],
            'resolved' => ['label' => 'Resolvidos/encerrados', 'type' => 'number'],
            'open' => ['label' => 'Em aberto', 'type' => 'number'],
            'first_response_hours' => ['label' => '1ª resposta (h)', 'type' => 'number', 'decimals' => 1],
            'resolution_hours' => ['label' => 'Resolução (h)', 'type' => 'number', 'decimals' => 1],
        ];
    }

    public function rows(array $filters): iterable
    {
        $groups = $this->aggregateQuery($filters)
            ->addSelect('assigned_to')
            ->groupBy('assigned_to')
            ->orderByDesc('received')
            ->toBase()
            ->get();

        $names = $this->userNames($groups->pluck('assigned_to'));

        return $groups->map(fn (object $group): array => [
            'assignee' => $group->assigned_to ? ($names[$group->assigned_to] ?? 'Usuário removido') : 'Sem atendente',
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
        $hours = fn (?float $value): string => $value === null ? '—' : number_format($value, 1, ',', '.').' h';

        return [
            ['label' => 'Chamados recebidos', 'value' => (string) $overall['received'], 'icon' => 'inbox', 'color' => 'brand'],
            ['label' => 'Resolvidos/encerrados', 'value' => (string) $overall['resolved'], 'icon' => 'check-circle', 'color' => 'green', 'hint' => $overall['open'].' em aberto'],
            ['label' => 'Primeira resposta (média)', 'value' => $hours($overall['first_response_hours']), 'icon' => 'chat-bubble', 'color' => 'blue'],
            ['label' => 'Resolução (média)', 'value' => $hours($overall['resolution_hours']), 'icon' => 'clock', 'color' => 'purple'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    private function aggregateQuery(array $filters): Builder
    {
        [$from, $to] = $this->period($filters);
        $resolved = self::sqlList([TicketStatus::Resolved, TicketStatus::Closed]);

        return Ticket::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['categoria'] ?? null, fn (Builder $query, string $category) => $query->where('ticket_category_id', (int) $category))
            ->selectRaw('COUNT(*) AS received')
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ({$resolved})) AS resolved")
            ->selectRaw('COUNT(*) FILTER (WHERE status IN ('.self::sqlList(TicketStatus::open()).')) AS open')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at)) / 3600) FILTER (WHERE first_response_at IS NOT NULL) AS first_response_hours')
            ->selectRaw("AVG(EXTRACT(EPOCH FROM (COALESCE(resolved_at, closed_at) - created_at)) / 3600) FILTER (WHERE status IN ({$resolved}) AND COALESCE(resolved_at, closed_at) IS NOT NULL) AS resolution_hours");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{received: int, resolved: int, open: int, first_response_hours: float|null, resolution_hours: float|null}
     */
    private function overall(array $filters): array
    {
        return $this->remember('overall', $filters, fn (): array => $this->metrics($this->aggregateQuery($filters)->toBase()->first()));
    }

    /**
     * @return array{received: int, resolved: int, open: int, first_response_hours: float|null, resolution_hours: float|null}
     */
    private function metrics(object $row): array
    {
        return [
            'received' => (int) $row->received,
            'resolved' => (int) $row->resolved,
            'open' => (int) $row->open,
            'first_response_hours' => $row->first_response_hours === null ? null : round((float) $row->first_response_hours, 1),
            'resolution_hours' => $row->resolution_hours === null ? null : round((float) $row->resolution_hours, 1),
        ];
    }
}
