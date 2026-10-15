<?php

namespace App\Actions\Finance;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Services\AuditLogger;
use App\Services\Finance\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Lançamento avulso no extrato (ex.: tarifa bancária, rendimento, aporte),
 * sem título a pagar/receber associado. Também permite estorná-lo.
 */
class RecordManualTransaction
{
    public function __construct(
        private LedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{direction: string, amount_cents: int, occurred_on: string, description: string, financial_category_id?: int|null}  $data
     */
    public function handle(FinancialAccount $account, array $data): FinancialTransaction
    {
        return DB::transaction(function () use ($account, $data): FinancialTransaction {
            $transaction = $this->ledger->post(
                account: $account,
                direction: TransactionDirection::from($data['direction']),
                amountCents: (int) $data['amount_cents'],
                type: TransactionType::Manual,
                description: $data['description'],
                occurredOn: $data['occurred_on'],
                links: ['financial_category_id' => $data['financial_category_id'] ?? null],
            );

            $this->audit->log('transaction.manual', $account, null, [
                'transaction_id' => $transaction->id,
                'direction' => $data['direction'],
                'amount_cents' => (int) $data['amount_cents'],
            ], "Lançamento avulso: {$data['description']}");

            return $transaction;
        });
    }

    public function reverse(FinancialTransaction $transaction, string $reason): FinancialTransaction
    {
        if ($transaction->type !== TransactionType::Manual) {
            throw new BusinessRuleException('Somente lançamentos avulsos podem ser estornados por aqui. Pagamentos e transferências possuem estorno próprio.');
        }

        return DB::transaction(function () use ($transaction, $reason): FinancialTransaction {
            $reversal = $this->ledger->reverse($transaction, $reason);

            $this->audit->log('transaction.reversed', $transaction->account, null, [
                'transaction_id' => $transaction->id,
                'reversal_id' => $reversal->id,
                'reason' => $reason,
            ]);

            return $reversal;
        });
    }
}
