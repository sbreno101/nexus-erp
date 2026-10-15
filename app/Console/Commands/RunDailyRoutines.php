<?php

namespace App\Console\Commands;

use App\Enums\CompanyStatus;
use App\Jobs\GenerateContractChargesJob;
use App\Jobs\GenerateRecurringExpensesJob;
use App\Jobs\NotifyOverdueReceivablesJob;
use App\Jobs\ProcessDailyCommercialRoutinesJob;
use App\Models\Company;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Enfileira as rotinas diárias de cada empresa ativa.
 *
 * Executado pelo Scheduler (routes/console.php), mas pode ser rodado à mão:
 *   php artisan erp:daily-routines            # enfileira (precisa do queue:work)
 *   php artisan erp:daily-routines --sync     # executa na hora, sem fila
 *   php artisan erp:daily-routines --company=1
 */
#[Signature('erp:daily-routines {--company= : Processar somente a empresa com este ID} {--sync : Executar imediatamente, sem usar a fila}')]
#[Description('Gera cobranças recorrentes e despesas recorrentes, expira propostas, processa vigência de contratos e avisa vencidos')]
class RunDailyRoutines extends Command
{
    public function handle(): int
    {
        $companies = Company::query()
            ->where('status', CompanyStatus::Active)
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($companies as $company) {
            $jobs = [
                new ProcessDailyCommercialRoutinesJob($company),
                new GenerateContractChargesJob($company),
                new GenerateRecurringExpensesJob($company),
                new NotifyOverdueReceivablesJob($company),
            ];

            foreach ($jobs as $job) {
                $this->option('sync') ? dispatch_sync($job) : dispatch($job);
            }

            $this->components->info('Rotinas '.($this->option('sync') ? 'executadas' : 'enfileiradas')." para {$company->display_name}.");
        }

        return self::SUCCESS;
    }
}
