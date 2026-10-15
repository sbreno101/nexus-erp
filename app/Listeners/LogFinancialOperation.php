<?php

namespace App\Listeners;

use App\Events\PaymentRecorded;
use App\Events\PaymentReversed;
use Illuminate\Support\Facades\Log;

/**
 * Log técnico ESTRUTURADO (JSON, canal "finance") das operações financeiras.
 *
 * Complementa a auditoria (que é consultável na tela) com um registro
 * pronto para ferramentas de observabilidade. Não registra dados pessoais:
 * apenas identificadores e valores.
 */
class LogFinancialOperation
{
    public function handle(PaymentRecorded|PaymentReversed $event): void
    {
        $payment = $event->payment;

        Log::channel('finance')->info($event instanceof PaymentRecorded ? 'payment.recorded' : 'payment.reversed', [
            'company_id' => $payment->company_id,
            'payment_id' => $payment->id,
            'receivable_id' => $payment->receivable_id,
            'payable_id' => $payment->payable_id,
            'financial_account_id' => $payment->financial_account_id,
            'amount_cents' => $payment->amount_cents,
            'user_id' => $payment->user_id,
        ]);
    }
}
