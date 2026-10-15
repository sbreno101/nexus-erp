<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Um pagamento/recebimento foi registrado.
 *
 * Disparado somente APÓS o commit da transação (ShouldDispatchAfterCommit):
 * se a operação for desfeita, ninguém é notificado.
 */
class PaymentRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
