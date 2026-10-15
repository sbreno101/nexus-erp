<?php

namespace App\Jobs;

use App\Actions\Finance\GenerateRecurringExpenses;
use App\Jobs\Concerns\RunsForCompany;
use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Gera as contas a pagar das despesas recorrentes de UMA empresa.
 */
class GenerateRecurringExpensesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RunsForCompany;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Company $company) {}

    public function handle(GenerateRecurringExpenses $action): void
    {
        $created = $this->withinCompany(fn (Company $company) => $action->forCurrentCompany(
            $company->settingsOrDefault()->billing_days_in_advance,
        ));

        Log::info('billing.recurring_expenses_generated', ['company_id' => $this->company->id, 'created' => $created]);
    }
}
