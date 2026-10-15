<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CancelBill;
use App\Enums\FinancialCategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PayableRequest;
use App\Http\Requests\Finance\ReasonRequest;
use App\Models\CostCenter;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\Payable;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Contas a pagar (despesas). Comprovantes são anexados na tela de detalhe.
 */
class PayableController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('create', Payable::class);

        return view('finance.bills.create', [
            'kind' => 'payable',
            'bill' => new Payable([
                'supplier_id' => $request->integer('supplier_id') ?: null,
                'issue_date' => today(),
                'due_date' => today()->addDays(10),
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(PayableRequest $request): RedirectResponse
    {
        $this->authorize('create', Payable::class);

        $payable = Payable::create([...$request->validated(), 'created_by' => Auth::id()]);

        return redirect()->route('payables.show', $payable)->with('success', 'Conta a pagar lançada.');
    }

    public function show(Payable $payable): View
    {
        $this->authorize('view', $payable);

        $payable->load(['supplier', 'recurringExpense', 'category', 'costCenter', 'canceller', 'payments.account', 'payments.user', 'payments.reverser', 'attachments.uploader']);

        return view('finance.bills.show', [
            'kind' => 'payable',
            'bill' => $payable,
            'accounts' => FinancialAccount::query()->active()->orderBy('name')->get()
                ->mapWithKeys(fn ($account) => [$account->id => "{$account->name} (saldo ".money($account->current_balance_cents).')'])->all(),
            'idempotencyKey' => (string) Str::uuid(),
            'auditLogs' => $payable->auditLogs()->with('user')->limit(20)->get(),
        ]);
    }

    public function edit(Payable $payable): View
    {
        $this->authorize('update', $payable);

        return view('finance.bills.edit', ['kind' => 'payable', 'bill' => $payable, ...$this->formOptions()]);
    }

    public function update(PayableRequest $request, Payable $payable): RedirectResponse
    {
        $this->authorize('update', $payable);

        $payable->update($request->validated());

        return redirect()->route('payables.show', $payable)->with('success', 'Conta a pagar atualizada.');
    }

    public function cancel(ReasonRequest $request, Payable $payable, CancelBill $cancelBill): RedirectResponse
    {
        $this->authorize('cancel', $payable);

        $cancelBill->handle($payable, $request->validated('reason'));

        return back()->with('success', 'Conta a pagar cancelada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'parties' => Supplier::query()->active()->orderBy('name')->get(['id', 'name', 'trade_name'])->mapWithKeys(fn ($supplier) => [$supplier->id => $supplier->display_name])->all(),
            'contracts' => [],
            'categories' => FinancialCategory::query()->ofType(FinancialCategoryType::Expense)->pluck('name', 'id')->all(),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn ($center) => [$center->id => $center->label()])->all(),
        ];
    }
}
