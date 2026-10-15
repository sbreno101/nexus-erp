<?php

namespace App\Events;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * O status de uma proposta mudou. Ouvido por:
 *   - MarkOpportunityAsWon: proposta aprovada => oportunidade ganha no CRM;
 *   - SendProposalStatusNotification: avisa o responsável pela proposta.
 */
class ProposalStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Proposal $proposal,
        public ProposalStatus $from,
        public ProposalStatus $to,
        public ?User $changedBy = null,
    ) {}
}
