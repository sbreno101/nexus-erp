<?php

namespace App\Actions\Proposals;

use App\Enums\ProposalStatus;
use App\Events\ProposalStatusChanged;
use App\Exceptions\BusinessRuleException;
use App\Models\Proposal;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Altera o status de uma proposta respeitando a máquina de estados do
 * Enum ProposalStatus (enviar, aprovar, recusar, expirar, cancelar, reabrir).
 */
class ChangeProposalStatus
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Proposal $proposal, ProposalStatus $target, ?string $reason = null): Proposal
    {
        $from = $proposal->status;

        $updated = DB::transaction(function () use ($proposal, $target, $reason, $from): Proposal {
            /** @var Proposal $locked */
            $locked = Proposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($target)) {
                throw new BusinessRuleException(sprintf(
                    'Não é possível alterar a proposta de "%s" para "%s".',
                    $locked->status->label(),
                    $target->label(),
                ));
            }

            $this->guardBusinessRules($locked, $target, $reason);

            $locked->status = $target;

            match ($target) {
                ProposalStatus::Sent => $locked->forceFill(['sent_at' => now()]),
                ProposalStatus::Approved => $locked->forceFill(['approved_at' => now(), 'approved_by' => Auth::id()]),
                ProposalStatus::Rejected => $locked->forceFill(['rejected_at' => now(), 'rejection_reason' => $reason]),
                ProposalStatus::Cancelled => $locked->forceFill(['cancelled_at' => now()]),
                ProposalStatus::Draft => $locked->forceFill(['approved_at' => null, 'approved_by' => null, 'rejected_at' => null, 'rejection_reason' => null]),
                ProposalStatus::Expired => $locked,
            };

            $locked->save();
            $locked->recordStatusChange($from, $target, $reason);
            $this->audit->log("proposal.{$target->value}", $locked, ['status' => $from->value], ['status' => $target->value, 'reason' => $reason]);

            return $locked;
        });

        $proposal->setRawAttributes($updated->getAttributes(), sync: true);

        /** @var User|null $user */
        $user = Auth::user();
        ProposalStatusChanged::dispatch($updated, $from, $target, $user);

        return $updated;
    }

    private function guardBusinessRules(Proposal $proposal, ProposalStatus $target, ?string $reason): void
    {
        if ($target === ProposalStatus::Sent && ! $proposal->items()->exists()) {
            throw new BusinessRuleException('Adicione pelo menos um item antes de enviar a proposta.');
        }

        if ($target === ProposalStatus::Approved && $proposal->isPastValidity()) {
            throw new BusinessRuleException('A validade desta proposta expirou. Volte-a para rascunho e atualize a validade antes de aprovar.');
        }

        if ($target === ProposalStatus::Rejected && blank($reason)) {
            throw new BusinessRuleException('Informe o motivo da recusa.');
        }

        if ($target === ProposalStatus::Cancelled && $proposal->contract()->exists()) {
            throw new BusinessRuleException('Esta proposta já originou um contrato. Cancele o contrato em vez da proposta.');
        }
    }
}
