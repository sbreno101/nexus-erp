<?php

namespace App\Actions\Finance;

use App\Enums\EntryStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\FinancialAccount;
use App\Models\Transfer;
use App\Services\AuditLogger;
use App\Services\Finance\LedgerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Transfere valores entre duas contas financeiras da empresa.
 *
 * As duas contas são travadas SEMPRE na mesma ordem (por id) para evitar
 * deadlock: se A→B e B→A acontecerem juntas, ambas tentam travar primeiro
 * a conta de menor id, e uma simplesmente espera a outra.
 */
class CreateTransfer
{
    public function __construct(
        private LedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{from_account_id: int, to_account_id: int, amount_cents: int, transferred_on: string, description?: string|null, idempotency_key: string}  $data
     */
    public function handle(array $data): Transfer
    {
        if ($existing = Transfer::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
            return $existing;
        }

        if ((int) $data['from_account_id'] === (int) $data['to_account_id']) {
            throw new BusinessRuleException('As contas de origem e destino devem ser diferentes.');
        }

        try {
            return DB::transaction(function () use ($data): Transfer {
                $accounts = FinancialAccount::query()
                    ->whereIn('id', [$data['from_account_id'], $data['to_account_id']])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $from = $accounts->get((int) $data['from_account_id']) ?? throw new BusinessRuleException('Conta de origem não encontrada.');
                $to = $accounts->get((int) $data['to_account_id']) ?? throw new BusinessRuleException('Conta de destino não encontrada.');

                $transfer = Transfer::create([
                    'from_account_id' => $from->id,
                    'to_account_id' => $to->id,
                    'amount_cents' => (int) $data['amount_cents'],
                    'transferred_on' => $data['transferred_on'],
                    'description' => $data['description'] ?? null,
                    'status' => EntryStatus::Confirmed,
                    'idempotency_key' => $data['idempotency_key'],
                    'user_id' => Auth::id(),
                ]);

                $label = $transfer->description ?: 'Transferência entre contas';

                // A saída valida o saldo da origem (não permite saldo negativo).
                $this->ledger->post($from, TransactionDirection::Out, $transfer->amount_cents, TransactionType::TransferOut,
                    "{$label} → {$to->name}", $transfer->transferred_on, ['transfer_id' => $transfer->id]);

                $this->ledger->post($to, TransactionDirection::In, $transfer->amount_cents, TransactionType::TransferIn,
                    "{$label} ← {$from->name}", $transfer->transferred_on, ['transfer_id' => $transfer->id]);

                $this->audit->log('transfer.created', $transfer, null, [
                    'from_account_id' => $from->id,
                    'to_account_id' => $to->id,
                    'amount_cents' => $transfer->amount_cents,
                ], 'Transferência de '.money($transfer->amount_cents)." de {$from->name} para {$to->name}.");

                return $transfer;
            });
        } catch (UniqueConstraintViolationException) {
            return Transfer::query()->where('idempotency_key', $data['idempotency_key'])->firstOrFail();
        }
    }
}
