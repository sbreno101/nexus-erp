<?php

namespace App\Console\Commands;

use App\Models\FinancialAccount;
use App\Models\Scopes\CompanyScope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Confere se o saldo materializado de cada conta financeira é igual ao saldo
 * recalculado a partir do extrato (saldo inicial + entradas − saídas).
 *
 * Uma divergência indica alteração indevida direto no banco ou um bug — por
 * isso o comando retorna código de erro e registra um alerta no log.
 */
#[Signature('finance:verify-balances')]
#[Description('Confere o saldo de todas as contas financeiras contra o extrato')]
class VerifyAccountBalances extends Command
{
    public function handle(): int
    {
        $divergent = 0;
        $rows = [];

        FinancialAccount::query()->withoutGlobalScope(CompanyScope::class)->with('company')->orderBy('company_id')->orderBy('id')
            ->each(function (FinancialAccount $account) use (&$divergent, &$rows): void {
                $ledger = $account->ledgerBalanceCents();
                $isConsistent = $ledger === $account->current_balance_cents;
                $divergent += $isConsistent ? 0 : 1;

                $rows[] = [
                    $account->company?->display_name,
                    $account->name,
                    money($account->current_balance_cents),
                    money($ledger),
                    $isConsistent ? 'OK' : 'DIVERGENTE',
                ];

                if (! $isConsistent) {
                    Log::channel('finance')->critical('finance.balance_mismatch', [
                        'financial_account_id' => $account->id,
                        'stored_cents' => $account->current_balance_cents,
                        'ledger_cents' => $ledger,
                    ]);
                }
            });

        $this->table(['Empresa', 'Conta', 'Saldo registrado', 'Saldo do extrato', 'Situação'], $rows);

        if ($divergent > 0) {
            $this->components->error("{$divergent} conta(s) com divergência de saldo.");

            return self::FAILURE;
        }

        $this->components->info('Todos os saldos conferem com os extratos.');

        return self::SUCCESS;
    }
}
