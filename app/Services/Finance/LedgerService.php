<?php

namespace App\Services\Finance;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Livro-razão: ÚNICO ponto do sistema que movimenta saldo de contas.
 *
 * Toda entrada ou saída de dinheiro (recebimento, pagamento, transferência,
 * lançamento avulso, estorno) passa por post(). O método:
 *   1. trava a linha da conta (SELECT ... FOR UPDATE) para evitar que duas
 *      operações simultâneas leiam o mesmo saldo e uma sobrescreva a outra;
 *   2. valida o saldo (saídas não podem deixar a conta negativa);
 *   3. grava o lançamento com o saldo resultante (balance_after_cents);
 *   4. atualiza o saldo materializado da conta.
 *
 * Deve ser chamado DENTRO de uma transação de banco (DB::transaction), que
 * é aberta pela Action de negócio: se qualquer passo falhar, nada é gravado.
 */
class LedgerService
{
    /**
     * @param  array{payment_id?: int|null, transfer_id?: int|null, financial_category_id?: int|null, reversal_of_id?: int|null}  $links
     */
    public function post(
        FinancialAccount $account,
        TransactionDirection $direction,
        int $amountCents,
        TransactionType $type,
        string $description,
        CarbonInterface|string $occurredOn,
        array $links = [],
        bool $allowNegativeBalance = false,
    ): FinancialTransaction {
        $this->ensureInsideTransaction();

        if ($amountCents <= 0) {
            throw new BusinessRuleException('O valor do lançamento deve ser maior que zero.');
        }

        /** @var FinancialAccount $lockedAccount */
        $lockedAccount = FinancialAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

        if (! $lockedAccount->is_active && ! $allowNegativeBalance) {
            throw new BusinessRuleException("A conta \"{$lockedAccount->name}\" está inativa e não aceita novos lançamentos.");
        }

        $newBalance = $lockedAccount->current_balance_cents + ($direction->sign() * $amountCents);

        if ($newBalance < 0 && $direction === TransactionDirection::Out && ! $allowNegativeBalance) {
            throw new BusinessRuleException(sprintf(
                'Saldo insuficiente na conta "%s". Saldo disponível: %s.',
                $lockedAccount->name,
                money($lockedAccount->current_balance_cents),
            ));
        }

        $transaction = FinancialTransaction::create([
            'financial_account_id' => $lockedAccount->id,
            'type' => $type,
            'direction' => $direction,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $newBalance,
            'description' => mb_substr($description, 0, 255),
            'occurred_on' => Carbon::parse($occurredOn)->toDateString(),
            'financial_category_id' => $links['financial_category_id'] ?? null,
            'payment_id' => $links['payment_id'] ?? null,
            'transfer_id' => $links['transfer_id'] ?? null,
            'reversal_of_id' => $links['reversal_of_id'] ?? null,
            'user_id' => Auth::id(),
        ]);

        // Atualização atômica e relativa ("saldo = saldo + x"), nunca "saldo = valor lido".
        $lockedAccount->newQuery()->whereKey($lockedAccount->id)
            ->increment('current_balance_cents', $direction->sign() * $amountCents);

        $account->current_balance_cents = $newBalance;

        return $transaction;
    }

    /**
     * Estorna um lançamento criando outro, de mesmo valor e sentido oposto.
     * O índice UNIQUE em reversal_of_id impede estornar duas vezes.
     */
    public function reverse(FinancialTransaction $original, string $reason, ?CarbonInterface $date = null): FinancialTransaction
    {
        $this->ensureInsideTransaction();

        if ($original->reversal_of_id !== null) {
            throw new BusinessRuleException('Um estorno não pode ser estornado.');
        }

        if ($original->reversal()->exists()) {
            throw new BusinessRuleException('Este lançamento já foi estornado.');
        }

        return $this->post(
            account: $original->account,
            direction: $original->direction->opposite(),
            amountCents: $original->amount_cents,
            type: TransactionType::Reversal,
            description: "Estorno: {$original->description} — {$reason}",
            occurredOn: $date ?? now(),
            links: [
                'payment_id' => $original->payment_id,
                'transfer_id' => $original->transfer_id,
                'financial_category_id' => $original->financial_category_id,
                'reversal_of_id' => $original->id,
            ],
            allowNegativeBalance: true,
        );
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Operações do livro-razão devem ocorrer dentro de DB::transaction().');
        }
    }
}
