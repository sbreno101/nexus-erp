<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Catálogo dos relatórios disponíveis.
 *
 * É o ÚNICO lugar que conhece todas as classes de relatório: a URL traz só a
 * chave ("contas-a-receber") e o registro devolve a instância correspondente.
 * Usar uma lista fechada (em vez de montar o nome da classe a partir da URL)
 * impede que alguém instancie classes arbitrárias manipulando o endereço.
 */
class ReportRegistry
{
    /**
     * Ordem desta lista = ordem de exibição dentro de cada grupo.
     *
     * @var array<string, class-string<Report>>
     */
    private const array REPORTS = [
        'clientes' => CustomersReport::class,
        'comercial' => CommercialReport::class,
        'contratos' => ContractsReport::class,
        'contas-a-receber' => ReceivablesReport::class,
        'contas-a-pagar' => PayablesReport::class,
        'fluxo-de-caixa' => CashFlowReport::class,
        'inadimplencia' => DelinquencyReport::class,
        'ordens-de-servico' => ServiceOrdersReport::class,
        'produtividade-atendimento' => SupportProductivityReport::class,
    ];

    /**
     * Ordem dos grupos na tela inicial.
     *
     * @var list<string>
     */
    private const array GROUPS = ['Comercial', 'Financeiro', 'Operacional'];

    /**
     * @throws NotFoundHttpException quando a chave não existe (404).
     */
    public function resolve(string $key): Report
    {
        abort_unless(array_key_exists($key, self::REPORTS), 404);

        return app(self::REPORTS[$key]);
    }

    /**
     * @return Collection<int, Report>
     */
    public function all(): Collection
    {
        return collect(self::REPORTS)->map(fn (string $class): Report => app($class))->values();
    }

    /**
     * Relatórios que o usuário pode abrir (de acordo com as permissões do
     * perfil dele na empresa ativa).
     *
     * @return Collection<int, Report>
     */
    public function visibleTo(User $user): Collection
    {
        return $this->all()->filter(fn (Report $report): bool => $user->can($report->permission()))->values();
    }

    /**
     * Relatórios visíveis agrupados por grupo, na ordem Comercial → Financeiro → Operacional.
     *
     * @return Collection<string, Collection<int, Report>>
     */
    public function groupedFor(User $user): Collection
    {
        return $this->visibleTo($user)
            ->groupBy(fn (Report $report): string => $report->group())
            ->sortBy(fn (Collection $reports, string $group): int|false => array_search($group, self::GROUPS, true));
    }
}
