<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Finance\ReversePayment;
use App\Http\Requests\Finance\ReasonRequest;
use App\Http\Resources\V1\PaymentResource;
use App\Models\Payment;

class PaymentController extends ApiController
{
    /**
     * POST /payments/{id}/reverse  {"reason": "..."}
     */
    public function reverse(ReasonRequest $request, Payment $payment, ReversePayment $reversePayment): PaymentResource
    {
        $this->authorize('reverse', $payment);

        return new PaymentResource($reversePayment->handle($payment, $request->validated('reason')));
    }
}
