<?php

namespace App\Actions\Finance;

use App\Enums\EntryStatus;
use App\Events\PaymentReversed;
use App\Exceptions\BusinessRuleException;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\Finance\LedgerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Estorna um pagamento: devolve o valor ao saldo em aberto do título e
 * lança no extrato um movimento inverso. O pagamento original continua
 * existindo, marcado como "estornado" (rastreabilidade completa).
 */
class ReversePayment
{
    public function __construct(
        private LedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    public function handle(Payment $payment, string $reason): Payment
    {
        $reversed = DB::transaction(function () use ($payment, $reason): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isReversed()) {
                throw new BusinessRuleException('Este pagamento já foi estornado.');
            }

            $bill = $locked->receivable_id
                ? $locked->receivable()->lockForUpdate()->firstOrFail()
                : $locked->payable()->lockForUpdate()->firstOrFail();

            $bill->revertPayment($locked->amount_cents);

            $originalEntry = $locked->transactions()->whereNull('reversal_of_id')->firstOrFail();
            $this->ledger->reverse($originalEntry, $reason);

            $locked->forceFill([
                'status' => EntryStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => Auth::id(),
                'reversal_reason' => $reason,
            ])->save();

            $this->audit->log('payment.reversed', $bill, ['payment_id' => $locked->id, 'status' => 'confirmed'], [
                'payment_id' => $locked->id,
                'status' => 'reversed',
                'reason' => $reason,
            ], 'Pagamento de '.money($locked->amount_cents).' estornado.');

            return $locked;
        });

        PaymentReversed::dispatch($reversed);

        return $reversed;
    }
}
