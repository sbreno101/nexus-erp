<?php

namespace App\Http\Controllers\Finance;

use App\Enums\BillingFrequency;
use App\Enums\FinancialCategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecurringExpenseRequest;
use App\Models\CostCenter;
use App\Models\FinancialCategory;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Despesas recorrentes: modelos a partir dos quais o Scheduler gera as
 * contas a pagar de cada período (Action GenerateRecurringExpenses).
 */
class RecurringExpenseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', RecurringExpense::class);

        return view('finance.recurring.index', [
            'expenses' => RecurringExpense::query()->with(['supplier:id,name,trade_name', 'category:id,name', 'costCenter:id,code,name'])
                ->withCount('payables')->orderByDesc('is_active')->orderBy('description')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', RecurringExpense::class);

        return view('finance.recurring.create', [
            'expense' => new RecurringExpense(['frequency' => BillingFrequency::Monthly, 'due_day' => 10, 'start_date' => today()->startOfMonth(), 'is_active' => true]),
            ...$this->formOptions(),
        ]);
    }

    public function store(RecurringExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', RecurringExpense::class);

        $data = $request->validated();

        RecurringExpense::create([
            ...$data,
            'next_due_date' => $this->firstDueDate($data['start_date'], (int) $data['due_day'])->toDateString(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('recurring-expenses.index')->with('success', 'Despesa recorrente cadastrada. As contas a pagar serão geradas automaticamente pela rotina diária.');
    }

    public function edit(RecurringExpense $recurringExpense): View
    {
        $this->authorize('update', $recurringExpense);

        return view('finance.recurring.edit', ['expense' => $recurringExpense, ...$this->formOptions()]);
    }

    /**
     * Alterações valem para os PRÓXIMOS períodos; contas a pagar já geradas não mudam.
     */
    public function update(RecurringExpenseRequest $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $this->authorize('update', $recurringExpense);

        $recurringExpense->update($request->validated());

        return redirect()->route('recurring-expenses.index')->with('success', 'Despesa recorrente atualizada (vale para os próximos períodos).');
    }

    public function destroy(RecurringExpense $recurringExpense): RedirectResponse
    {
        $this->authorize('delete', $recurringExpense);

        // Soft delete: as contas a pagar já geradas permanecem vinculadas.
        $recurringExpense->delete();

        return redirect()->route('recurring-expenses.index')->with('success', 'Despesa recorrente removida.');
    }

    private function firstDueDate(string $startDate, int $dueDay): CarbonImmutable
    {
        $start = CarbonImmutable::parse($startDate);
        $candidate = $start->setDay($dueDay);

        return $candidate->lt($start) ? $candidate->addMonthNoOverflow() : $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(['id', 'name', 'trade_name'])->mapWithKeys(fn ($supplier) => [$supplier->id => $supplier->display_name])->all(),
            'categories' => FinancialCategory::query()->ofType(FinancialCategoryType::Expense)->pluck('name', 'id')->all(),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn ($center) => [$center->id => $center->label()])->all(),
            'frequencies' => BillingFrequency::options(),
        ];
    }
}
