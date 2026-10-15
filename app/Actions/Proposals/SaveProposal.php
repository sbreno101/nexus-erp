<?php

namespace App\Actions\Proposals;

use App\Enums\ProposalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Proposal;
use App\Services\DocumentNumberGenerator;
use App\Services\LineItemCalculator;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cria ou atualiza uma proposta e seus itens, recalculando os totais.
 *
 * Os totais NUNCA vêm do navegador: o formulário envia apenas quantidade,
 * preço e desconto de cada item, e o LineItemCalculator calcula o resto
 * no servidor. Assim um usuário mal-intencionado não consegue "forjar" o total.
 */
class SaveProposal
{
    public function __construct(
        private LineItemCalculator $calculator,
        private DocumentNumberGenerator $numbers,
        private CurrentCompany $currentCompany,
    ) {}

    /**
     * @param  array<string, mixed>  $data  dados validados (ProposalRequest), incluindo "items"
     */
    public function create(array $data): Proposal
    {
        return DB::transaction(function () use ($data): Proposal {
            $settings = $this->currentCompany->getOrFail()->settingsOrDefault();
            $totals = $this->calculator->calculate($data['items'], (int) ($data['discount_cents'] ?? 0));
            $issueDate = $data['issue_date'] ?? today()->toDateString();

            $proposal = Proposal::create([
                ...Arr::except($data, ['items', 'discount_cents']),
                'number' => $this->numbers->next(DocumentNumberGenerator::PROPOSAL),
                'status' => ProposalStatus::Draft,
                'owner_id' => $data['owner_id'] ?? Auth::id(),
                'issue_date' => $issueDate,
                'valid_until' => $data['valid_until'] ?? today()->addDays($settings->proposal_validity_days)->toDateString(),
                'payment_terms' => $data['payment_terms'] ?? $settings->default_payment_terms,
                'subtotal_cents' => $totals['subtotal_cents'],
                'discount_cents' => $totals['discount_cents'],
                'total_cents' => $totals['total_cents'],
            ]);

            $proposal->items()->createMany($totals['items']);
            $proposal->recordStatusChange(null, ProposalStatus::Draft, 'Proposta criada.');

            return $proposal;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Proposal $proposal, array $data): Proposal
    {
        if (! $proposal->status->isEditable()) {
            throw new BusinessRuleException('Somente propostas em rascunho podem ser editadas. Volte a proposta para rascunho antes de alterá-la.');
        }

        return DB::transaction(function () use ($proposal, $data): Proposal {
            $totals = $this->calculator->calculate($data['items'], (int) ($data['discount_cents'] ?? 0));

            $proposal->update([
                ...Arr::except($data, ['items', 'discount_cents']),
                'subtotal_cents' => $totals['subtotal_cents'],
                'discount_cents' => $totals['discount_cents'],
                'total_cents' => $totals['total_cents'],
            ]);

            // Substitui os itens: mais simples e seguro do que comparar linha a linha.
            $proposal->items()->delete();
            $proposal->items()->createMany($totals['items']);

            return $proposal;
        });
    }
}
