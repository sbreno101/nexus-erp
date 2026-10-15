<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\RecordPayment;
use App\Actions\Finance\ReversePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReasonRequest;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Receivable;
use Illuminate\Http\RedirectResponse;

/**
 * Baixas (recebimentos/pagamentos) e estornos. Toda a proteção contra
 * duplicidade e concorrência está nas Actions RecordPayment/ReversePayment.
 */
class PaymentController extends Controller
{
    public function storeForReceivable(RecordPaymentRequest $request, Receivable $receivable, RecordPayment $recordPayment): RedirectResponse
    {
        $this->authorize('settle', $receivable);

        $payment = $recordPayment->handle($receivable, $request->validated());

        return back()->with('success', 'Recebimento de '.money($payment->amount_cents).' registrado.');
    }

    public function storeForPayable(RecordPaymentRequest $request, Payable $payable, RecordPayment $recordPayment): RedirectResponse
    {
        $this->authorize('settle', $payable);

        $payment = $recordPayment->handle($payable, $request->validated());

        return back()->with('success', 'Pagamento de '.money($payment->amount_cents).' registrado.');
    }

    public function reverse(ReasonRequest $request, Payment $payment, ReversePayment $reversePayment): RedirectResponse
    {
        $this->authorize('reverse', $payment);

        $reversePayment->handle($payment, $request->validated('reason'));

        return back()->with('success', 'Pagamento estornado. O valor voltou a ficar em aberto.');
    }
}
