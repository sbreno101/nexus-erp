<?php

namespace App\Actions\Finance;

use App\Enums\EntryStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\FinancialAccount;
use App\Models\Transfer;
use App\Services\AuditLogger;
use App\Services\Finance\LedgerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Estorna uma transferência: os dois lançamentos recebem movimentos inversos.
 */
class ReverseTransfer
{
    public function __construct(
        private LedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    public function handle(Transfer $transfer, string $reason): Transfer
    {
        return DB::transaction(function () use ($transfer, $reason): Transfer {
            /** @var Transfer $locked */
            $locked = Transfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->isReversed()) {
                throw new BusinessRuleException('Esta transferência já foi estornada.');
            }

            FinancialAccount::query()
                ->whereIn('id', [$locked->from_account_id, $locked->to_account_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $locked->transactions()->whereNull('reversal_of_id')->with('account')->get()
                ->each(fn ($entry) => $this->ledger->reverse($entry, $reason));

            $locked->forceFill([
                'status' => EntryStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => Auth::id(),
                'reversal_reason' => $reason,
            ])->save();

            $this->audit->log('transfer.reversed', $locked, ['status' => 'confirmed'], ['status' => 'reversed', 'reason' => $reason]);

            return $locked;
        });
    }
}
