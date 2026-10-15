<?php

use App\Actions\Finance\CancelBill;
use App\Actions\Finance\RecordPayment;
use App\Actions\Finance\ReversePayment;
use App\Enums\BillStatus;
use App\Enums\EntryStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Receivable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsRole('finance');
    $this->account = FinancialAccount::factory()->withBalance(100_000)->create();
    $this->receivable = Receivable::factory()->create(['amount_cents' => 50_000]);
});

function paymentData(FinancialAccount $account, int $amount, ?string $key = null): array
{
    return [
        'financial_account_id' => $account->id,
        'amount_cents' => $amount,
        'paid_on' => today()->toDateString(),
        'method' => 'pix',
        'idempotency_key' => $key ?? (string) Str::uuid(),
    ];
}

it('registra a baixa total de uma conta a receber e credita a conta financeira', function () {
    $payment = app(RecordPayment::class)->handle($this->receivable, paymentData($this->account, 50_000));

    expect($payment->status)->toBe(EntryStatus::Confirmed)
        ->and($this->receivable->fresh()->status)->toBe(BillStatus::Paid)
        ->and($this->receivable->fresh()->paid_at)->not->toBeNull()
        ->and($this->account->fresh()->current_balance_cents)->toBe(150_000)
        ->and(FinancialTransaction::where('payment_id', $payment->id)->count())->toBe(1);
});

it('aceita pagamentos parciais até quitar o título', function () {
    $action = app(RecordPayment::class);

    $action->handle($this->receivable, paymentData($this->account, 20_000));
    expect($this->receivable->fresh())
        ->status->toBe(BillStatus::Partial)
        ->paid_cents->toBe(20_000);

    $action->handle($this->receivable, paymentData($this->account, 30_000));
    expect($this->receivable->fresh())
        ->status->toBe(BillStatus::Paid)
        ->paid_cents->toBe(50_000);
});

it('não permite pagar mais que o saldo em aberto', function () {
    app(RecordPayment::class)->handle($this->receivable, paymentData($this->account, 50_001));
})->throws(BusinessRuleException::class);

it('é idempotente: a mesma chave não gera dois pagamentos', function () {
    $key = (string) Str::uuid();
    $action = app(RecordPayment::class);

    $first = $action->handle($this->receivable, paymentData($this->account, 10_000, $key));
    $second = $action->handle($this->receivable, paymentData($this->account, 10_000, $key));

    expect($second->id)->toBe($first->id)
        ->and(Payment::count())->toBe(1)
        ->and($this->receivable->fresh()->paid_cents)->toBe(10_000)
        ->and($this->account->fresh()->current_balance_cents)->toBe(110_000);
});

it('o banco rejeita chaves de idempotência duplicadas mesmo sem passar pela Action', function () {
    $data = paymentData($this->account, 1_000);
    app(RecordPayment::class)->handle($this->receivable, $data);

    Payment::create([...$data, 'receivable_id' => $this->receivable->id, 'status' => 'confirmed', 'method' => 'pix']);
})->throws(UniqueConstraintViolationException::class);

it('estorna um pagamento devolvendo o saldo do título e da conta', function () {
    $payment = app(RecordPayment::class)->handle($this->receivable, paymentData($this->account, 50_000));

    app(ReversePayment::class)->handle($payment, 'Pagamento lançado na conta errada');

    expect($payment->fresh()->status)->toBe(EntryStatus::Reversed)
        ->and($this->receivable->fresh())->status->toBe(BillStatus::Open)->paid_cents->toBe(0)
        ->and($this->account->fresh()->current_balance_cents)->toBe(100_000)
        // O lançamento original continua no extrato; o estorno é um novo lançamento.
        ->and(FinancialTransaction::where('payment_id', $payment->id)->count())->toBe(2);
});

it('não estorna o mesmo pagamento duas vezes', function () {
    $payment = app(RecordPayment::class)->handle($this->receivable, paymentData($this->account, 10_000));
    app(ReversePayment::class)->handle($payment, 'Erro');
    app(ReversePayment::class)->handle($payment, 'Erro de novo');
})->throws(BusinessRuleException::class, 'já foi estornado');

it('pagamento de conta a pagar debita a conta e respeita o saldo disponível', function () {
    $payable = Payable::factory()->create(['amount_cents' => 30_000]);
    app(RecordPayment::class)->handle($payable, paymentData($this->account, 30_000));

    expect($this->account->fresh()->current_balance_cents)->toBe(70_000)
        ->and($payable->fresh()->status)->toBe(BillStatus::Paid);

    $expensive = Payable::factory()->create(['amount_cents' => 500_000]);

    expect(fn () => app(RecordPayment::class)->handle($expensive, paymentData($this->account, 500_000)))
        ->toThrow(BusinessRuleException::class, 'Saldo insuficiente');

    // A transação foi desfeita por completo: nada foi gravado.
    expect($expensive->fresh()->paid_cents)->toBe(0)
        ->and($this->account->fresh()->current_balance_cents)->toBe(70_000);
});

it('cancela título sem pagamentos e bloqueia cancelamento com pagamentos', function () {
    $other = Receivable::factory()->create();
    app(CancelBill::class)->handle($other, 'Cobrança emitida em duplicidade');

    expect($other->fresh())
        ->status->toBe(BillStatus::Cancelled)
        ->cancellation_reason->toBe('Cobrança emitida em duplicidade');

    app(RecordPayment::class)->handle($this->receivable, paymentData($this->account, 1_000));

    expect(fn () => app(CancelBill::class)->handle($this->receivable, 'Teste'))
        ->toThrow(BusinessRuleException::class, 'Estorne os pagamentos');
});

it('não aceita pagamento em título cancelado', function () {
    app(CancelBill::class)->handle($this->receivable, 'Cancelado');
    app(RecordPayment::class)->handle($this->receivable->fresh(), paymentData($this->account, 1_000));
})->throws(BusinessRuleException::class);

it('mantém o saldo da conta igual ao extrato após várias operações', function () {
    $action = app(RecordPayment::class);
    $payment = $action->handle($this->receivable, paymentData($this->account, 25_000));
    $action->handle(Payable::factory()->create(['amount_cents' => 40_000]), paymentData($this->account, 40_000));
    app(ReversePayment::class)->handle($payment, 'Ajuste');

    $account = $this->account->fresh();

    expect($account->current_balance_cents)->toBe(60_000)
        ->and($account->ledgerBalanceCents())->toBe($account->current_balance_cents);
});

it('lançamentos do extrato são imutáveis', function () {
    $payment = app(RecordPayment::class)->handle($this->receivable, paymentData($this->account, 1_000));
    $entry = FinancialTransaction::where('payment_id', $payment->id)->firstOrFail();

    expect(fn () => $entry->update(['amount_cents' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});
