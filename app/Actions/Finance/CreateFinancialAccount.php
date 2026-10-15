<?php

namespace App\Actions\Finance;

use App\Models\FinancialAccount;
use Illuminate\Support\Facades\DB;

/**
 * Cadastra uma conta financeira. O saldo atual começa igual ao saldo inicial;
 * a partir daí, só o LedgerService o altera.
 *
 * O saldo inicial não pode ser editado depois (correções = lançamento avulso),
 * para que o extrato continue explicando 100% do saldo.
 */
class CreateFinancialAccount
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): FinancialAccount
    {
        return DB::transaction(function () use ($data): FinancialAccount {
            $account = new FinancialAccount($data);
            $account->current_balance_cents = (int) ($data['opening_balance_cents'] ?? 0);
            $account->save();

            return $account;
        });
    }
}
