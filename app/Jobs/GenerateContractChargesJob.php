<?php

namespace App\Jobs;

use App\Actions\Contracts\GenerateContractCharges;
use App\Jobs\Concerns\RunsForCompany;
use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Gera as cobranças recorrentes dos contratos ativos de UMA empresa.
 * Enfileirado diariamente pelo comando erp:daily-routines (Scheduler).
 */
class GenerateContractChargesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RunsForCompany;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Company $company) {}

    public function handle(GenerateContractCharges $action): void
    {
        $created = $this->withinCompany(fn () => $action->forCurrentCompany());

        Log::info('billing.contract_charges_generated', ['company_id' => $this->company->id, 'created' => $created]);
    }
}
