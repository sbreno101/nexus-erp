<?php

namespace App\Actions\Proposals;

use App\Actions\Contracts\ChangeContractStatus;
use App\Enums\BillingFrequency;
use App\Enums\ContractStatus;
use App\Enums\ProposalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Contract;
use App\Models\Proposal;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Converte uma proposta APROVADA em contrato.
 *
 * "Sem duplicar informações": cliente, itens, valores e condições vêm da
 * proposta; o usuário informa apenas o que é próprio do contrato (início,
 * periodicidade, dia de cobrança). A proposta fica vinculada ao contrato
 * (contracts.proposal_id, UNIQUE): uma proposta gera no máximo um contrato.
 */
class ConvertProposalToContract
{
    public function __construct(
        private DocumentNumberGenerator $numbers,
        private ChangeContractStatus $changeContractStatus,
        private CurrentCompany $currentCompany,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, billing_frequency?: string|null, billing_day?: int|null, auto_renew?: bool|null, financial_category_id?: int|null, activate?: bool|null}  $options
     */
    public function handle(Proposal $proposal, array $options = []): Contract
    {
        try {
            $contract = DB::transaction(fn (): Contract => $this->convert($proposal, $options));
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('Esta proposta já foi convertida em contrato.');
        }

        if (! empty($options['activate'])) {
            $this->changeContractStatus->handle($contract, ContractStatus::Active, 'Ativado na conversão da proposta.');
        }

        return $contract;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function convert(Proposal $proposal, array $options): Contract
    {
        /** @var Proposal $locked */
        $locked = Proposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== ProposalStatus::Approved) {
            throw new BusinessRuleException('Somente propostas aprovadas podem ser convertidas em contrato.');
        }

        if ($locked->contract()->exists()) {
            throw new BusinessRuleException('Esta proposta já foi convertida em contrato.');
        }

        $settings = $this->currentCompany->getOrFail()->settingsOrDefault();

        $contract = Contract::create([
            'number' => $this->numbers->next(DocumentNumberGenerator::CONTRACT),
            'customer_id' => $locked->customer_id,
            'proposal_id' => $locked->id,
            'owner_id' => $locked->owner_id ?? Auth::id(),
            'title' => $locked->title,
            'description' => $locked->notes,
            'status' => ContractStatus::Draft,
            'start_date' => $options['start_date'] ?? today()->toDateString(),
            'end_date' => $options['end_date'] ?? null,
            'billing_frequency' => $options['billing_frequency'] ?? BillingFrequency::Monthly->value,
            'billing_day' => $options['billing_day'] ?? $settings->default_billing_day,
            'amount_cents' => $locked->total_cents,
            'financial_category_id' => $options['financial_category_id'] ?? null,
            'auto_renew' => (bool) ($options['auto_renew'] ?? false),
            'notes' => $locked->payment_terms,
        ]);

        $items = $locked->items()->get()->map(fn ($item) => $item->only([
            'catalog_item_id', 'description', 'quantity', 'unit', 'unit_price_cents', 'discount_cents', 'total_cents', 'position',
        ]))->all();

        // Contratos não têm "desconto geral": ele é distribuído nos itens, para que a
        // soma dos itens seja sempre igual ao valor do contrato (inclusive após edições).
        $contract->items()->createMany($this->apportionDiscount($items, $locked->discount_cents));

        $contract->recordStatusChange(null, ContractStatus::Draft, "Gerado a partir da proposta {$locked->number}.");
        $this->audit->log('proposal.converted', $locked, null, ['contract_id' => $contract->id], "Proposta convertida no contrato {$contract->number}.");

        return $contract;
    }

    /**
     * Rateia um desconto (centavos) entre os itens, proporcionalmente ao total
     * de cada um, usando apenas aritmética inteira. A sobra do arredondamento
     * vai para o último item com saldo, de modo que a soma bata exatamente.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function apportionDiscount(array $items, int $discountCents): array
    {
        $base = array_sum(array_column($items, 'total_cents'));

        if ($discountCents <= 0 || $base <= 0) {
            return $items;
        }

        $remaining = $discountCents;
        $lastIndex = array_key_last(array_filter($items, fn ($item) => $item['total_cents'] > 0));

        foreach ($items as $index => $item) {
            $share = $index === $lastIndex ? $remaining : intdiv($item['total_cents'] * $discountCents, $base);
            $share = min($share, $item['total_cents']);
            $remaining -= $share;

            $items[$index]['discount_cents'] = $item['discount_cents'] + $share;
            $items[$index]['total_cents'] = $item['total_cents'] - $share;
        }

        // Caso extremo (item muito pequeno limitou a sobra): distribui o resto.
        foreach ($items as $index => $item) {
            if ($remaining <= 0) {
                break;
            }

            $extra = min($remaining, $item['total_cents']);
            $items[$index]['discount_cents'] += $extra;
            $items[$index]['total_cents'] -= $extra;
            $remaining -= $extra;
        }

        return $items;
    }
}
