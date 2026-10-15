<?php

namespace App\Jobs;

use App\Actions\Contracts\ProcessContractLifecycle;
use App\Actions\Proposals\ExpireProposals;
use App\Jobs\Concerns\RunsForCompany;
use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rotinas comerciais diárias de UMA empresa:
 *  - expira propostas enviadas com validade vencida;
 *  - renova ou encerra contratos cuja vigência terminou.
 */
class ProcessDailyCommercialRoutinesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RunsForCompany;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Company $company) {}

    public function handle(ExpireProposals $expireProposals, ProcessContractLifecycle $contractLifecycle): void
    {
        $result = $this->withinCompany(fn () => [
            'expired_proposals' => $expireProposals->forCurrentCompany(),
            ...$contractLifecycle->forCurrentCompany(),
        ]);

        Log::info('commercial.daily_routines', ['company_id' => $this->company->id, ...$result]);
    }
}
