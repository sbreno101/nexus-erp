<?php

namespace App\Listeners;

use App\Enums\ProposalStatus;
use App\Events\ProposalStatusChanged;
use App\Notifications\ProposalStatusChangedNotification;

/**
 * Avisa o responsável quando outra pessoa aprova ou recusa a proposta dele.
 * (Registrado automaticamente pelo "event discovery" do Laravel, a partir
 * do tipo do parâmetro do método handle().)
 */
class SendProposalStatusNotification
{
    public function handle(ProposalStatusChanged $event): void
    {
        $proposal = $event->proposal->loadMissing(['owner', 'company.settings']);
        $settings = $proposal->company->settingsOrDefault();

        if (! in_array($event->to, [ProposalStatus::Approved, ProposalStatus::Rejected], true)
            || ! $settings->notify_proposal_status
            || $proposal->owner === null
            || $proposal->owner->is($event->changedBy)) {
            return;
        }

        $proposal->owner->notify(new ProposalStatusChangedNotification($proposal, sendMail: true));
    }
}
