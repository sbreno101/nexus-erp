<?php

namespace App\Livewire\Finance;

use App\Enums\BillStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\CostCenter;
use App\Models\FinancialCategory;
use App\Models\Payable;
use App\Models\Receivable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Base das listagens de contas a receber e a pagar (mesmos filtros e totais).
 *
 * Herança aqui é justificada: as duas telas diferem apenas no Model, na
 * "parte" (cliente/fornecedor) e nas rotas — todo o resto é idêntico.
 */
abstract class BillIndex extends Component
{
    use WithDataTable;

    /**
     * Situação: "" (todas, exceto canceladas), open, overdue, upcoming, paid, cancelled.
     */
    #[Url(as: 'situacao', except: '')]
    public string $situation = '';

    #[Url(as: 'categoria', except: '')]
    public string $category = '';

    #[Url(as: 'centro', except: '')]
    public string $costCenter = '';

    #[Url(as: 'de', except: '')]
    public string $from = '';

    #[Url(as: 'ate', except: '')]
    public string $to = '';

    /**
     * @var list<string>
     */
    protected array $sortable = ['due_date', 'amount_cents', 'competence', 'description'];

    /**
     * @return class-string<Receivable|Payable>
     */
    abstract protected function modelClass(): string;

    abstract protected function kind(): string;

    abstract protected function partyRelation(): string;

    public function mount(): void
    {
        $this->authorize('viewAny', $this->modelClass());
    }

    public function render(): View
    {
        $model = $this->modelClass();
        $query = $model::query()
            ->with([$this->partyRelation().':id,name,trade_name', 'category:id,name'])
            ->search($this->search)
            ->when($this->category !== '', fn (Builder $query) => $query->where('financial_category_id', $this->category))
            ->when($this->costCenter !== '', fn (Builder $query) => $query->where('cost_center_id', $this->costCenter))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('due_date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('due_date', '<=', $this->to));

        match ($this->situation) {
            'open' => $query->whereIn('status', BillStatus::outstanding()),
            'overdue' => $query->overdue(),
            'upcoming' => $query->upcoming(),
            'paid' => $query->where('status', BillStatus::Paid),
            'cancelled' => $query->where('status', BillStatus::Cancelled),
            default => $query->where('status', '!=', BillStatus::Cancelled),
        };

        $totals = (clone $query)->reorder()->select([
            DB::raw('COUNT(*) AS total_count'),
            DB::raw('COALESCE(SUM(amount_cents), 0) AS amount'),
            DB::raw('COALESCE(SUM(paid_cents), 0) AS paid'),
            DB::raw("COALESCE(SUM(CASE WHEN status IN ('open', 'partial') THEN amount_cents - paid_cents ELSE 0 END), 0) AS outstanding"),
        ])->first();

        return view('livewire.finance.bill-index', [
            'kind' => $this->kind(),
            'partyRelation' => $this->partyRelation(),
            'bills' => $this->applySorting($query, 'due_date')->paginate($this->perPageForCompany()),
            'totals' => $totals,
            'categories' => FinancialCategory::query()->where('type', $this->kind() === 'receivable' ? 'income' : 'expense')->orderBy('name')->pluck('name', 'id'),
            'costCenters' => CostCenter::query()->orderBy('code')->get()->mapWithKeys(fn ($center) => [$center->id => $center->label()]),
        ]);
    }
}
