<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreateFinancialAccount;
use App\Actions\Finance\RecordManualTransaction;
use App\Enums\FinancialAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinancialAccountRequest;
use App\Http\Requests\Finance\ManualTransactionRequest;
use App\Http\Requests\Finance\ReasonRequest;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Contas financeiras internas e seus extratos (livro-razão).
 */
class FinancialAccountController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $accounts = FinancialAccount::query()->orderByDesc('is_active')->orderBy('name')->get();

        return view('finance.accounts.index', [
            'accounts' => $accounts,
            'totalBalance' => (int) $accounts->where('is_active', true)->sum('current_balance_cents'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', FinancialAccount::class);

        return view('finance.accounts.create', [
            'account' => new FinancialAccount(['opening_date' => today(), 'is_active' => true, 'opening_balance_cents' => 0]),
            'types' => FinancialAccountType::options(),
        ]);
    }

    public function store(FinancialAccountRequest $request, CreateFinancialAccount $createAccount): RedirectResponse
    {
        $this->authorize('create', FinancialAccount::class);

        $account = $createAccount->handle($request->validated());

        return redirect()->route('accounts.show', $account)->with('success', 'Conta financeira cadastrada.');
    }

    /**
     * Extrato do período com saldo anterior, lançamentos e saldo final.
     */
    public function show(Request $request, FinancialAccount $account): View
    {
        $this->authorize('view', $account);

        $from = $this->parseDate($request->query('de'), today()->startOfMonth());
        $to = $this->parseDate($request->query('ate'), today());

        $previousBalance = $account->opening_balance_cents + (int) $account->transactions()
            ->where('occurred_on', '<', $from->toDateString())
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE -amount_cents END), 0) AS total")
            ->value('total');

        $transactions = $account->transactions()
            ->with(['category:id,name', 'user:id,name', 'payment.receivable:id,description', 'payment.payable:id,description', 'reversal:id,reversal_of_id'])
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        // Saldo corrido do extrato, calculado na ordem cronológica.
        $running = $previousBalance;
        $statement = $transactions->map(function (FinancialTransaction $transaction) use (&$running) {
            $running += $transaction->signedAmountCents();

            return ['transaction' => $transaction, 'balance' => $running];
        });

        return view('finance.accounts.show', [
            'account' => $account,
            'statement' => $statement,
            'previousBalance' => $previousBalance,
            'inflow' => (int) $transactions->where('direction.value', 'in')->sum('amount_cents'),
            'outflow' => (int) $transactions->where('direction.value', 'out')->sum('amount_cents'),
            'from' => $from,
            'to' => $to,
            'categories' => FinancialCategory::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get()
                ->mapWithKeys(fn ($category) => [$category->id => $category->type->label().' — '.$category->name])->all(),
            'ledgerMatches' => $account->ledgerBalanceCents() === $account->current_balance_cents,
        ]);
    }

    public function edit(FinancialAccount $account): View
    {
        $this->authorize('update', $account);

        return view('finance.accounts.edit', ['account' => $account, 'types' => FinancialAccountType::options()]);
    }

    public function update(FinancialAccountRequest $request, FinancialAccount $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $account->update($request->validated());

        return redirect()->route('accounts.show', $account)->with('success', 'Conta financeira atualizada.');
    }

    public function storeTransaction(ManualTransactionRequest $request, FinancialAccount $account, RecordManualTransaction $record): RedirectResponse
    {
        $this->authorize('update', $account);

        $record->handle($account, $request->validated());

        return back()->with('success', 'Lançamento registrado.');
    }

    public function reverseTransaction(ReasonRequest $request, FinancialTransaction $transaction, RecordManualTransaction $record): RedirectResponse
    {
        $this->authorize('update', $transaction->account);

        $record->reverse($transaction, $request->validated('reason'));

        return back()->with('success', 'Lançamento estornado.');
    }

    private function parseDate(?string $value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
