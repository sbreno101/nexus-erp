<?php

namespace App\Actions\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Proposal;

/**
 * Marca como "Expirada" toda proposta ENVIADA cuja validade já passou.
 * Executada diariamente pelo Scheduler (veja routes/console.php).
 */
class ExpireProposals
{
    public function __construct(private ChangeProposalStatus $changeStatus) {}

    public function forCurrentCompany(): int
    {
        $expired = 0;

        Proposal::query()
            ->where('status', ProposalStatus::Sent)
            ->whereDate('valid_until', '<', today())
            ->eachById(function (Proposal $proposal) use (&$expired): void {
                $this->changeStatus->handle($proposal, ProposalStatus::Expired, 'Validade expirada (rotina automática).');
                $expired++;
            });

        return $expired;
    }
}
