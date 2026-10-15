<?php

namespace App\Actions\Finance;

use App\Enums\BillStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Payable;
use App\Models\Receivable;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cancela uma conta a receber ou a pagar.
 *
 * Regra: um título com pagamentos confirmados não pode ser cancelado
 * diretamente — primeiro os pagamentos devem ser estornados. Isso evita
 * "dinheiro órfão" no extrato sem título correspondente.
 */
class CancelBill
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Receivable|Payable $bill, string $reason): Receivable|Payable
    {
        return DB::transaction(function () use ($bill, $reason): Receivable|Payable {
            /** @var Receivable|Payable $locked */
            $locked = $bill->newQuery()->whereKey($bill->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === BillStatus::Cancelled) {
                throw new BusinessRuleException('Este título já está cancelado.');
            }

            if ($locked->paid_cents > 0) {
                throw new BusinessRuleException('Este título possui pagamentos registrados. Estorne os pagamentos antes de cancelá-lo.');
            }

            $previousStatus = $locked->status->value;

            $locked->forceFill([
                'status' => BillStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => $reason,
            ])->save();

            $event = $locked instanceof Receivable ? 'receivable.cancelled' : 'payable.cancelled';
            $this->audit->log($event, $locked, ['status' => $previousStatus], ['status' => 'cancelled', 'reason' => $reason]);

            return $locked;
        });
    }
}
