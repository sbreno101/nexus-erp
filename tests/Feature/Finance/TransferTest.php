<?php

use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\RecordManualTransaction;
use App\Actions\Finance\ReverseTransfer;
use App\Enums\EntryStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\FinancialAccount;
use App\Models\Transfer;
use App\Services\Dashboard\DashboardMetrics;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsRole('finance');
    $this->from = FinancialAccount::factory()->withBalance(100_000)->create();
    $this->to = FinancialAccount::factory()->withBalance(0)->create();
});

function transferData(FinancialAccount $from, FinancialAccount $to, int $amount, ?string $key = null): array
{
    return [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount_cents' => $amount,
        'transferred_on' => today()->toDateString(),
        'idempotency_key' => $key ?? (string) Str::uuid(),
    ];
}

it('transfere entre contas e mantém os saldos consistentes com o extrato', function () {
    app(CreateTransfer::class)->handle(transferData($this->from, $this->to, 30_000));

    $from = $this->from->fresh();
    $to = $this->to->fresh();

    expect($from->current_balance_cents)->toBe(70_000)
        ->and($to->current_balance_cents)->toBe(30_000)
        ->and($from->ledgerBalanceCents())->toBe(70_000)
        ->and($to->ledgerBalanceCents())->toBe(30_000);
});

it('não permite transferir mais que o saldo da origem', function () {
    app(CreateTransfer::class)->handle(transferData($this->from, $this->to, 100_001));
})->throws(BusinessRuleException::class, 'Saldo insuficiente');

it('não permite transferir para a mesma conta', function () {
    app(CreateTransfer::class)->handle(transferData($this->from, $this->from, 1_000));
})->throws(BusinessRuleException::class);

it('é idempotente', function () {
    $key = (string) Str::uuid();
    app(CreateTransfer::class)->handle(transferData($this->from, $this->to, 10_000, $key));
    app(CreateTransfer::class)->handle(transferData($this->from, $this->to, 10_000, $key));

    expect(Transfer::count())->toBe(1)
        ->and($this->to->fresh()->current_balance_cents)->toBe(10_000);
});

it('estorna transferências', function () {
    $transfer = app(CreateTransfer::class)->handle(transferData($this->from, $this->to, 40_000));
    app(ReverseTransfer::class)->handle($transfer, 'Conta de destino incorreta');

    expect($transfer->fresh()->status)->toBe(EntryStatus::Reversed)
        ->and($this->from->fresh()->current_balance_cents)->toBe(100_000)
        ->and($this->to->fresh()->current_balance_cents)->toBe(0);
});

it('registra e estorna lançamentos avulsos', function () {
    $action = app(RecordManualTransaction::class);

    $fee = $action->handle($this->from, [
        'direction' => 'out',
        'amount_cents' => 1_590,
        'occurred_on' => today()->toDateString(),
        'description' => 'Tarifa bancária mensal',
    ]);

    expect($this->from->fresh()->current_balance_cents)->toBe(98_410);

    $action->reverse($fee, 'Tarifa devolvida pelo banco');

    expect($this->from->fresh()->current_balance_cents)->toBe(100_000)
        ->and($this->from->fresh()->ledgerBalanceCents())->toBe(100_000);
});

it('o comando de conferência de saldos aponta divergências', function () {
    $this->artisan('finance:verify-balances')->assertSuccessful();

    // Simula uma alteração indevida feita direto no banco.
    FinancialAccount::query()->whereKey($this->from->id)->update(['current_balance_cents' => 1]);

    $this->artisan('finance:verify-balances')->assertFailed();
});

it('transferências estornadas não inflam entradas e saídas do fluxo de caixa consolidado', function () {
    $transfer = app(CreateTransfer::class)->handle(transferData($this->from, $this->to, 40_000));
    app(ReverseTransfer::class)->handle($transfer, 'Conta de destino incorreta');
    app(RecordManualTransaction::class)->handle($this->from, ['direction' => 'in', 'amount_cents' => 5_000, 'occurred_on' => today()->toDateString(), 'description' => 'Rendimento']);

    $metrics = new DashboardMetrics([currentCompany()->id], today()->startOfMonth()->toImmutable(), today()->endOfMonth()->toImmutable());
    $cashFlow = $metrics->cashFlow();

    expect(array_sum($cashFlow['inflow']))->toBe(5_000)
        ->and(array_sum($cashFlow['outflow']))->toBe(0)
        ->and(end($cashFlow['balance']))->toBe(105_000);
});
