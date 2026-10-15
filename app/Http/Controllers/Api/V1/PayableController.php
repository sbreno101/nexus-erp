<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Finance\CancelBill;
use App\Actions\Finance\RecordPayment;
use App\Http\Controllers\Api\V1\Concerns\HandlesBills;
use App\Http\Requests\Finance\PayableRequest;
use App\Http\Requests\Finance\ReasonRequest;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Resources\V1\BillResource;
use App\Http\Resources\V1\PaymentResource;
use App\Models\Payable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PayableController extends ApiController
{
    use HandlesBills;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payable::class);

        $query = $this->applyBillFilters(Payable::query()->with('supplier:id,name,trade_name'), $request)
            ->when($request->query('supplier_id'), fn ($query, $supplierId) => $query->where('supplier_id', $supplierId));

        return BillResource::collection($this->sort($query, $request, ['due_date', 'amount_cents', 'competence'], 'due_date')->paginate($this->perPage($request)));
    }

    public function store(PayableRequest $request): JsonResponse
    {
        $this->authorize('create', Payable::class);

        $payable = Payable::create([...$request->validated(), 'created_by' => Auth::id()]);

        return (new BillResource($payable->load('supplier')))->response()->setStatusCode(201);
    }

    public function show(Payable $payable): BillResource
    {
        $this->authorize('view', $payable);

        return new BillResource($payable->load(['supplier', 'payments']));
    }

    public function pay(RecordPaymentRequest $request, Payable $payable, RecordPayment $recordPayment): JsonResponse
    {
        $this->authorize('settle', $payable);

        $payment = $recordPayment->handle($payable, $request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }

    public function cancel(ReasonRequest $request, Payable $payable, CancelBill $cancelBill): BillResource
    {
        $this->authorize('cancel', $payable);

        return new BillResource($cancelBill->handle($payable, $request->validated('reason'))->load('supplier'));
    }
}
