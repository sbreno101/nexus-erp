<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\ReverseTransfer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReasonRequest;
use App\Http\Requests\Finance\TransferRequest;
use App\Models\FinancialAccount;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function create(): View
    {
        $this->authorize('transfers.create');

        $accounts = FinancialAccount::query()->active()->orderBy('name')->get();

        return view('finance.transfers.create', [
            'accounts' => $accounts->mapWithKeys(fn ($account) => [$account->id => "{$account->name} (saldo ".money($account->current_balance_cents).')'])->all(),
            'idempotencyKey' => (string) Str::uuid(),
            'recent' => Transfer::query()->with(['fromAccount:id,name', 'toAccount:id,name', 'user:id,name'])->latest()->limit(10)->get(),
        ]);
    }

    public function store(TransferRequest $request, CreateTransfer $createTransfer): RedirectResponse
    {
        $this->authorize('transfers.create');

        $transfer = $createTransfer->handle($request->validated());

        return redirect()->route('transfers.create')->with('success', 'Transferência de '.money($transfer->amount_cents).' realizada.');
    }

    public function reverse(ReasonRequest $request, Transfer $transfer, ReverseTransfer $reverseTransfer): RedirectResponse
    {
        $this->authorize('reverse', $transfer);

        $reverseTransfer->handle($transfer, $request->validated('reason'));

        return back()->with('success', 'Transferência estornada.');
    }
}
