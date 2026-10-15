<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Finance\CancelBill;
use App\Actions\Finance\RecordPayment;
use App\Http\Controllers\Api\V1\Concerns\HandlesBills;
use App\Http\Requests\Finance\ReasonRequest;
use App\Http\Requests\Finance\ReceivableRequest;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Resources\V1\BillResource;
use App\Http\Resources\V1\PaymentResource;
use App\Models\Receivable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Contas a receber via API. A baixa exige "idempotency_key" (UUID): repetir
 * a mesma requisição (ex.: retry após timeout) devolve o mesmo pagamento.
 */
class ReceivableController extends ApiController
{
    use HandlesBills;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Receivable::class);

        $query = $this->applyBillFilters(Receivable::query()->with('customer:id,name,trade_name'), $request)
            ->when($request->query('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId));

        return BillResource::collection($this->sort($query, $request, ['due_date', 'amount_cents', 'competence'], 'due_date')->paginate($this->perPage($request)));
    }

    public function store(ReceivableRequest $request): JsonResponse
    {
        $this->authorize('create', Receivable::class);

        $receivable = Receivable::create([...$request->validated(), 'created_by' => Auth::id()]);

        return (new BillResource($receivable->load('customer')))->response()->setStatusCode(201);
    }

    public function show(Receivable $receivable): BillResource
    {
        $this->authorize('view', $receivable);

        return new BillResource($receivable->load(['customer', 'payments']));
    }

    public function pay(RecordPaymentRequest $request, Receivable $receivable, RecordPayment $recordPayment): JsonResponse
    {
        $this->authorize('settle', $receivable);

        $payment = $recordPayment->handle($receivable, $request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }

    public function cancel(ReasonRequest $request, Receivable $receivable, CancelBill $cancelBill): BillResource
    {
        $this->authorize('cancel', $receivable);

        return new BillResource($cancelBill->handle($receivable, $request->validated('reason'))->load('customer'));
    }
}
