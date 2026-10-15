<?php

namespace App\Reports;

use App\Enums\BillStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Payable;
use App\Models\Receivable;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fluxo de caixa: realizado x previsto, por mês (ou por dia em períodos curtos).
 *
 * - REALIZADO: o que de fato entrou/saiu das contas, segundo o extrato
 *   (financial_transactions). Inclui recebimentos, pagamentos, lançamentos
 *   avulsos e estornos.
 * - PREVISTO: o que ainda está em aberto (saldo devedor) nas contas a receber
 *   e a pagar com vencimento dentro de cada intervalo.
 * - SALDO ACUMULADO: parte do saldo das contas imediatamente antes do período
 *   (saldo inicial de abertura + todos os movimentos anteriores) e soma o
 *   resultado realizado de cada intervalo.
 *
 * Transferências entre contas são desconsideradas na visão consolidada (o
 * dinheiro só muda de conta, o total da empresa não muda). Quando UMA conta é
 * filtrada, elas passam a contar, pois alteram o saldo daquela conta.
 */
class CashFlowReport extends Report
{
    /**
     * Períodos de até 31 dias são exibidos dia a dia; acima disso, por mês.
     */
    private const int DAILY_LIMIT_DAYS = 31;

    public function key(): string
    {
        return 'fluxo-de-caixa';
    }

    public function title(): string
    {
        return 'Fluxo de caixa';
    }

    public function description(): string
    {
        return 'Entradas e saídas realizadas, valores previstos em aberto e saldo acumulado.';
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
        return 'arrows-right-left';
    }

    public function filters(): array
    {
        return [
            // Padrão: últimos 6 meses, incluindo o mês atual.
            'de' => ['type' => 'date', 'label' => 'De', 'default' => now()->subMonthsNoOverflow(5)->startOfMonth()->toDateString()],
            'ate' => ['type' => 'date', 'label' => 'Até', 'default' => now()->endOfMonth()->toDateString()],
            'conta' => [
                'type' => 'select',
                'label' => 'Conta financeira',
                'options' => FinancialAccount::query()->orderBy('name')->pluck('name', 'id')->all(),
                'placeholder' => 'Todas as contas',
                'default' => '',
            ],
        ];
    }

    public function columns(): array
    {
        return [
            'period' => ['label' => 'Período', 'type' => 'text'],
            'inflow_cents' => ['label' => 'Entradas', 'type' => 'money'],
            'outflow_cents' => ['label' => 'Saídas', 'type' => 'money'],
            'net_cents' => ['label' => 'Resultado', 'type' => 'money'],
            'balance_cents' => ['label' => 'Saldo acumulado', 'type' => 'money'],
            'forecast_in_cents' => ['label' => 'A receber (previsto)', 'type' => 'money'],
            'forecast_out_cents' => ['label' => 'A pagar (previsto)', 'type' => 'money'],
            'forecast_net_cents' => ['label' => 'Resultado previsto', 'type' => 'money'],
        ];
    }

    public function rows(array $filters): iterable
    {
        return $this->remember('rows', $filters, fn (): array => $this->buildRows($filters));
    }

    public function totals(array $filters): array
    {
        $rows = collect($this->rows($filters));

        return [
            '_label' => 'Total do período',
            'inflow_cents' => $rows->sum('inflow_cents'),
            'outflow_cents' => $rows->sum('outflow_cents'),
            'net_cents' => $rows->sum('net_cents'),
            'balance_cents' => $rows->last()['balance_cents'] ?? $this->openingBalance($filters),
            'forecast_in_cents' => $rows->sum('forecast_in_cents'),
            'forecast_out_cents' => $rows->sum('forecast_out_cents'),
            'forecast_net_cents' => $rows->sum('forecast_net_cents'),
        ];
    }

    public function summary(array $filters): array
    {
        $totals = $this->totals($filters);

        return [
            ['label' => 'Saldo inicial', 'value' => money($this->openingBalance($filters)), 'icon' => 'building-library', 'color' => 'brand', 'hint' => 'Antes de '.br_date($filters['de'])],
            ['label' => 'Entradas realizadas', 'value' => money($totals['inflow_cents']), 'icon' => 'arrow-trending-up', 'color' => 'green'],
            ['label' => 'Saídas realizadas', 'value' => money($totals['outflow_cents']), 'icon' => 'arrow-trending-down', 'color' => 'red'],
            ['label' => 'Saldo final realizado', 'value' => money($totals['balance_cents']), 'icon' => 'banknotes', 'color' => 'blue', 'hint' => 'Previsto em aberto: '.money($totals['forecast_net_cents'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $daily = (int) $from->diffInDays($to->startOfDay()) + 1 <= self::DAILY_LIMIT_DAYS;

        // Formato do "balde" de agrupamento no PostgreSQL (to_char) e no PHP.
        // São constantes do código (não vêm do usuário), por isso podem ir no SQL.
        $sqlFormat = $daily ? 'YYYY-MM-DD' : 'YYYY-MM';
        $phpFormat = $daily ? 'Y-m-d' : 'Y-m';
        $range = [$from->toDateString(), $to->toDateString()];

        $realized = $this->transactions($filters)
            ->whereBetween('occurred_on', $range)
            ->selectRaw("to_char(occurred_on, '{$sqlFormat}') AS bucket")
            ->selectRaw("COALESCE(SUM(amount_cents) FILTER (WHERE direction = 'in'), 0) AS inflow")
            ->selectRaw("COALESCE(SUM(amount_cents) FILTER (WHERE direction = 'out'), 0) AS outflow")
            ->groupBy('bucket')
            ->toBase()
            ->get()
            ->keyBy('bucket');

        $forecastIn = $this->forecast(Receivable::query(), $sqlFormat, $range);
        $forecastOut = $this->forecast(Payable::query(), $sqlFormat, $range);

        $rows = [];
        $running = $this->openingBalance($filters);

        foreach ($this->buckets($from, $to, $daily) as $bucket) {
            $key = $bucket->format($phpFormat);
            $inflow = (int) ($realized[$key]->inflow ?? 0);
            $outflow = (int) ($realized[$key]->outflow ?? 0);
            $expectedIn = (int) ($forecastIn[$key] ?? 0);
            $expectedOut = (int) ($forecastOut[$key] ?? 0);
            $running += $inflow - $outflow;

            $rows[] = [
                'period' => $daily ? $bucket->format('d/m/Y') : ucfirst($bucket->translatedFormat('M/Y')),
                'inflow_cents' => $inflow,
                'outflow_cents' => $outflow,
                'net_cents' => $inflow - $outflow,
                'balance_cents' => $running,
                'forecast_in_cents' => $expectedIn,
                'forecast_out_cents' => $expectedOut,
                'forecast_net_cents' => $expectedIn - $expectedOut,
            ];
        }

        return $rows;
    }

    /**
     * Lançamentos do extrato considerados no fluxo (respeitando o filtro de conta).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<FinancialTransaction>
     */
    private function transactions(array $filters): Builder
    {
        $accountId = $filters['conta'] ?? null;

        return FinancialTransaction::query()
            ->when(
                $accountId,
                fn (Builder $query) => $query->where('financial_account_id', (int) $accountId),
                // Sem filtro de conta, transferências (e seus estornos) se anulam no total.
                fn (Builder $query) => $query->whereNull('transfer_id'),
            );
    }

    /**
     * Saldo imediatamente antes do período: saldo de abertura das contas +
     * todos os movimentos anteriores à data inicial.
     *
     * @param  array<string, mixed>  $filters
     */
    private function openingBalance(array $filters): int
    {
        return $this->remember('opening', $filters, function () use ($filters): int {
            $accountId = $filters['conta'] ?? null;

            // withTrashed(): o extrato de uma conta excluída continua no histórico,
            // então o saldo de abertura dela também precisa entrar na conta.
            $opening = (int) FinancialAccount::withTrashed()
                ->when($accountId, fn (Builder $query) => $query->whereKey((int) $accountId))
                ->sum('opening_balance_cents');

            $previousMovements = (int) $this->transactions($filters)
                ->where('occurred_on', '<', $filters['de'])
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE -amount_cents END), 0) AS total")
                ->value('total');

            return $opening + $previousMovements;
        });
    }

    /**
     * Saldo em aberto dos títulos por intervalo de vencimento.
     *
     * @param  Builder<Receivable>|Builder<Payable>  $query
     * @param  array{0: string, 1: string}  $range
     * @return array<string, int>
     */
    private function forecast(Builder $query, string $sqlFormat, array $range): array
    {
        return $query
            ->whereIn('status', BillStatus::outstanding())
            ->whereBetween('due_date', $range)
            ->selectRaw("to_char(due_date, '{$sqlFormat}') AS bucket")
            ->selectRaw('SUM(amount_cents - paid_cents) AS total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Intervalos (dias ou meses) do período, inclusive os sem movimento.
     *
     * @return list<CarbonImmutable>
     */
    private function buckets(CarbonImmutable $from, CarbonImmutable $to, bool $daily): array
    {
        $period = $daily
            ? CarbonPeriod::create($from->startOfDay(), '1 day', $to->startOfDay())
            : CarbonPeriod::create($from->startOfMonth(), '1 month', $to->startOfMonth());

        return array_map(fn ($date): CarbonImmutable => CarbonImmutable::instance($date), iterator_to_array($period, false));
    }
}
