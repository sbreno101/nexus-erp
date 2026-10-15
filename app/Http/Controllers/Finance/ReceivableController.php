<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CancelBill;
use App\Enums\FinancialCategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReasonRequest;
use App\Http\Requests\Finance\ReceivableRequest;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\Receivable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Contas a receber (a listagem fica no Livewire ReceivableIndex).
 * Títulos não são excluídos: são cancelados (com motivo).
 */
class ReceivableController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('create', Receivable::class);

        return view('finance.bills.create', [
            'kind' => 'receivable',
            'bill' => new Receivable([
                'customer_id' => $request->integer('customer_id') ?: null,
                'issue_date' => today(),
                'due_date' => today()->addDays(10),
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(ReceivableRequest $request): RedirectResponse
    {
        $this->authorize('create', Receivable::class);

        $receivable = Receivable::create([...$request->validated(), 'created_by' => Auth::id()]);

        return redirect()->route('receivables.show', $receivable)->with('success', 'Conta a receber lançada.');
    }

    public function show(Receivable $receivable): View
    {
        $this->authorize('view', $receivable);

        $receivable->load(['customer', 'contract', 'category', 'costCenter', 'canceller', 'payments.account', 'payments.user', 'payments.reverser', 'attachments.uploader']);

        return view('finance.bills.show', [
            'kind' => 'receivable',
            'bill' => $receivable,
            'accounts' => FinancialAccount::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'idempotencyKey' => (string) Str::uuid(),
            'auditLogs' => $receivable->auditLogs()->with('user')->limit(20)->get(),
        ]);
    }

    public function edit(Receivable $receivable): View
    {
        $this->authorize('update', $receivable);

        return view('finance.bills.edit', ['kind' => 'receivable', 'bill' => $receivable, ...$this->formOptions()]);
    }

    public function update(ReceivableRequest $request, Receivable $receivable): RedirectResponse
    {
        $this->authorize('update', $receivable);

        $receivable->update($request->validated());

        return redirect()->route('receivables.show', $receivable)->with('success', 'Conta a receber atualizada.');
    }

    public function cancel(ReasonRequest $request, Receivable $receivable, CancelBill $cancelBill): RedirectResponse
    {
        $this->authorize('cancel', $receivable);

        $cancelBill->handle($receivable, $request->validated('reason'));

        return back()->with('success', 'Conta a receber cancelada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'parties' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'trade_name'])->mapWithKeys(fn ($customer) => [$customer->id => $customer->display_name])->all(),
            'contracts' => Contract::query()->with('customer:id,name,trade_name')->whereIn('status', ['active', 'suspended'])->orderBy('number')->get()
                ->map(fn ($contract) => ['id' => $contract->id, 'customer_id' => $contract->customer_id, 'label' => "{$contract->number} — {$contract->title}"])->all(),
            'categories' => FinancialCategory::query()->ofType(FinancialCategoryType::Income)->pluck('name', 'id')->all(),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn ($center) => [$center->id => $center->label()])->all(),
        ];
    }
}
