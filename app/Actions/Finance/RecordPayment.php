<?php

namespace App\Actions\Finance;

use App\Enums\EntryStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Events\PaymentRecorded;
use App\Exceptions\BusinessRuleException;
use App\Models\FinancialAccount;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Receivable;
use App\Services\AuditLogger;
use App\Services\Finance\LedgerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registra a baixa (total ou parcial) de uma conta a receber ou a pagar.
 *
 * Proteções contra processamento duplicado:
 *   1. Idempotência: cada formulário/requisição envia uma idempotency_key
 *      (UUID). Se a chave já foi usada, devolvemos o pagamento existente em
 *      vez de criar outro (ex.: duplo clique, reenvio do navegador, retry da API).
 *   2. Índice UNIQUE (company_id, idempotency_key): mesmo que duas
 *      requisições idênticas cheguem ao MESMO tempo, o banco aceita só uma.
 *   3. Lock pessimista no título (lockForUpdate): impede que dois pagamentos
 *      simultâneos ultrapassem o saldo em aberto.
 *   4. CHECK (paid_cents <= amount_cents) no banco, como última barreira.
 */
class RecordPayment
{
    public function __construct(
        private LedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{financial_account_id: int, amount_cents: int, paid_on: string, method: string, notes?: string|null, idempotency_key: string}  $data
     */
    public function handle(Receivable|Payable $bill, array $data): Payment
    {
        if ($existing = $this->findByIdempotencyKey($data['idempotency_key'])) {
            return $this->guardSameTarget($existing, $bill);
        }

        try {
            $payment = DB::transaction(fn (): Payment => $this->process($bill, $data));
        } catch (UniqueConstraintViolationException) {
            // Corrida entre duas requisições com a mesma chave: a outra venceu.
            return $this->guardSameTarget($this->findByIdempotencyKey($data['idempotency_key']), $bill);
        }

        PaymentRecorded::dispatch($payment);

        return $payment;
    }

    /**
     * @param  array{financial_account_id: int, amount_cents: int, paid_on: string, method: string, notes?: string|null, idempotency_key: string}  $data
     */
    private function process(Receivable|Payable $bill, array $data): Payment
    {
        /** @var Receivable|Payable $lockedBill */
        $lockedBill = $bill->newQuery()->whereKey($bill->getKey())->lockForUpdate()->firstOrFail();
        $amount = (int) $data['amount_cents'];
        $paidOn = Carbon::parse($data['paid_on']);

        if (! $lockedBill->status->acceptsPayment()) {
            throw new BusinessRuleException("Este título está \"{$lockedBill->status->label()}\" e não aceita pagamentos.");
        }

        if ($amount <= 0) {
            throw new BusinessRuleException('O valor do pagamento deve ser maior que zero.');
        }

        if ($amount > $lockedBill->outstandingCents()) {
            throw new BusinessRuleException('O valor informado ('.money($amount).') é maior que o saldo em aberto ('.money($lockedBill->outstandingCents()).').');
        }

        $account = FinancialAccount::query()->findOrFail($data['financial_account_id']);
        $isReceivable = $lockedBill instanceof Receivable;

        $payment = Payment::create([
            'receivable_id' => $isReceivable ? $lockedBill->id : null,
            'payable_id' => $isReceivable ? null : $lockedBill->id,
            'financial_account_id' => $account->id,
            'amount_cents' => $amount,
            'paid_on' => $paidOn->toDateString(),
            'method' => PaymentMethod::from($data['method']),
            'notes' => $data['notes'] ?? null,
            'status' => EntryStatus::Confirmed,
            'idempotency_key' => $data['idempotency_key'],
            'user_id' => Auth::id(),
        ]);

        $lockedBill->applyPayment($amount, $paidOn);

        $this->ledger->post(
            account: $account,
            direction: $isReceivable ? TransactionDirection::In : TransactionDirection::Out,
            amountCents: $amount,
            type: $isReceivable ? TransactionType::Receipt : TransactionType::Payment,
            description: ($isReceivable ? 'Recebimento: ' : 'Pagamento: ').$lockedBill->description,
            occurredOn: $paidOn,
            links: ['payment_id' => $payment->id, 'financial_category_id' => $lockedBill->financial_category_id],
        );

        $this->audit->log('payment.recorded', $lockedBill, null, [
            'payment_id' => $payment->id,
            'amount_cents' => $amount,
            'paid_on' => $paidOn->toDateString(),
            'financial_account_id' => $account->id,
        ], 'Pagamento de '.money($amount).' registrado.');

        $bill->setRawAttributes($lockedBill->getAttributes(), sync: true);

        return $payment;
    }

    private function findByIdempotencyKey(string $key): ?Payment
    {
        return Payment::query()->where('idempotency_key', $key)->first();
    }

    private function guardSameTarget(?Payment $payment, Receivable|Payable $bill): Payment
    {
        $foreignKey = $bill instanceof Receivable ? 'receivable_id' : 'payable_id';

        if ($payment === null || (int) $payment->{$foreignKey} !== $bill->id) {
            throw new BusinessRuleException('Esta operação já foi processada para outro título. Recarregue a página e tente novamente.');
        }

        return $payment;
    }
}
