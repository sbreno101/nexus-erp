<?php

namespace App\Livewire\Finance;

use App\Enums\BillStatus;
use App\Models\FinancialAccount;
use App\Models\Payable;
use App\Models\Receivable;
use App\Reports\CashFlowReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Fluxo de caixa: realizado (extrato) x previsto (títulos em aberto).
 *
 * Reaproveita o CashFlowReport: a tela e o relatório exportável usam
 * exatamente a mesma regra de cálculo (uma única fonte de verdade).
 */
#[Title('Fluxo de caixa')]
class CashFlow extends Component
{
    #[Url(as: 'de', except: '')]
    public string $from = '';

    #[Url(as: 'ate', except: '')]
    public string $to = '';

    #[Url(as: 'conta', except: '')]
    public string $account = '';

    public function mount(): void
    {
        $this->authorize('cash_flow.view');
    }

    public function render(): View
    {
        $report = app(CashFlowReport::class);
        $filters = $report->normalizeFilters(['de' => $this->from, 'ate' => $this->to, 'conta' => $this->account]);
        $rows = collect($report->rows($filters));

        $horizon = today()->addDays(15);

        return view('livewire.finance.cash-flow', [
            'filters' => $filters,
            'rows' => $rows,
            'totals' => $report->totals($filters),
            'summary' => $report->summary($filters),
            'accounts' => FinancialAccount::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'upcomingReceivables' => Receivable::query()->with('customer:id,name,trade_name')->whereIn('status', BillStatus::outstanding())
                ->whereDate('due_date', '<=', $horizon)->orderBy('due_date')->limit(8)->get(),
            'upcomingPayables' => Payable::query()->with('supplier:id,name,trade_name')->whereIn('status', BillStatus::outstanding())
                ->whereDate('due_date', '<=', $horizon)->orderBy('due_date')->limit(8)->get(),
            'chart' => [
                'type' => 'bar',
                'money' => true,
                'data' => [
                    'labels' => $rows->pluck('period')->all(),
                    'datasets' => [
                        ['type' => 'line', 'label' => 'Saldo acumulado (realizado)', 'data' => $rows->pluck('balance_cents')->all(), 'borderColor' => '#6366f1', 'backgroundColor' => '#6366f1', 'tension' => 0.3],
                        ['label' => 'Entradas', 'data' => $rows->pluck('inflow_cents')->all(), 'backgroundColor' => '#10b981', 'borderRadius' => 3],
                        ['label' => 'Saídas', 'data' => $rows->pluck('outflow_cents')->all(), 'backgroundColor' => '#ef4444', 'borderRadius' => 3],
                        ['label' => 'A receber (previsto)', 'data' => $rows->pluck('forecast_in_cents')->all(), 'backgroundColor' => 'rgba(16,185,129,0.35)', 'borderRadius' => 3],
                        ['label' => 'A pagar (previsto)', 'data' => $rows->pluck('forecast_out_cents')->all(), 'backgroundColor' => 'rgba(239,68,68,0.35)', 'borderRadius' => 3],
                    ],
                ],
            ],
        ]);
    }
}
