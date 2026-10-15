<?php

namespace App\Listeners;

use App\Actions\Crm\MoveOpportunity;
use App\Enums\OpportunityStatus;
use App\Enums\ProposalStatus;
use App\Events\ProposalStatusChanged;
use App\Models\PipelineStage;

/**
 * Integração Comercial -> CRM: quando uma proposta vinculada a uma
 * oportunidade é aprovada, a oportunidade é movida para a etapa de "ganho".
 */
class MarkOpportunityAsWon
{
    public function __construct(private MoveOpportunity $moveOpportunity) {}

    public function handle(ProposalStatusChanged $event): void
    {
        if ($event->to !== ProposalStatus::Approved || $event->proposal->opportunity_id === null) {
            return;
        }

        $opportunity = $event->proposal->opportunity()->first();

        if ($opportunity === null || $opportunity->status !== OpportunityStatus::Open) {
            return;
        }

        $wonStage = PipelineStage::query()
            ->where('company_id', $opportunity->company_id)
            ->where('is_won', true)
            ->ordered()
            ->first();

        if ($wonStage !== null) {
            $this->moveOpportunity->handle($opportunity, $wonStage);
        }
    }
}
