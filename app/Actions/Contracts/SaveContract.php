<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Contract;
use App\Services\DocumentNumberGenerator;
use App\Services\LineItemCalculator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cria ou atualiza um contrato manualmente (sem proposta de origem).
 *
 * O valor periódico (amount_cents) é a soma dos itens contratados.
 */
class SaveContract
{
    public function __construct(
        private LineItemCalculator $calculator,
        private DocumentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contract
    {
        return DB::transaction(function () use ($data): Contract {
            $totals = $this->calculator->calculate($data['items']);

            $contract = Contract::create([
                ...Arr::except($data, ['items']),
                'number' => $this->numbers->next(DocumentNumberGenerator::CONTRACT),
                'status' => ContractStatus::Draft,
                'owner_id' => $data['owner_id'] ?? Auth::id(),
                'amount_cents' => $totals['total_cents'],
            ]);

            $contract->items()->createMany($totals['items']);
            $contract->recordStatusChange(null, ContractStatus::Draft, 'Contrato cadastrado.');

            return $contract;
        });
    }

    /**
     * Alterações em contratos ativos ficam registradas na auditoria
     * (valores anteriores e novos), compondo o histórico de alterações.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        if (! $contract->status->isEditable()) {
            throw new BusinessRuleException("Contratos com status \"{$contract->status->label()}\" não podem ser alterados.");
        }

        return DB::transaction(function () use ($contract, $data): Contract {
            $totals = $this->calculator->calculate($data['items']);

            $contract->update([
                ...Arr::except($data, ['items']),
                'amount_cents' => $totals['total_cents'],
            ]);

            $contract->items()->delete();
            $contract->items()->createMany($totals['items']);

            return $contract;
        });
    }
}
