<?php

use App\Actions\Contracts\SaveContract;
use App\Actions\Proposals\ChangeProposalStatus;
use App\Actions\Proposals\ConvertProposalToContract;
use App\Actions\Proposals\ExpireProposals;
use App\Actions\Proposals\SaveProposal;
use App\Enums\ContractStatus;
use App\Enums\OpportunityStatus;
use App\Enums\ProposalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CatalogItem;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Receivable;
use App\Notifications\ProposalStatusChangedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = actingAsRole('sales');
    $this->customer = Customer::factory()->create();
    $this->service = CatalogItem::factory()->create(['price_cents' => 20_000, 'unit' => 'H']);
});

function proposalPayload(Customer $customer, CatalogItem $service, array $overrides = []): array
{
    return [
        'customer_id' => $customer->id,
        'title' => 'Implantação do Nexus ERP',
        'discount_cents' => 5_000,
        'items' => [
            ['catalog_item_id' => $service->id, 'description' => $service->name, 'quantity' => '10', 'unit' => 'H', 'unit_price_cents' => 20_000, 'discount_cents' => 0],
            ['description' => 'Treinamento', 'quantity' => '1', 'unit' => 'UN', 'unit_price_cents' => 50_000, 'discount_cents' => 10_000],
        ],
        ...$overrides,
    ];
}

it('cria proposta com numeração sequencial e totais calculados no servidor', function () {
    $action = app(SaveProposal::class);

    $first = $action->create(proposalPayload($this->customer, $this->service));
    $second = $action->create(proposalPayload($this->customer, $this->service));

    expect($first->number)->toBe('PROP-'.now()->year.'-00001')
        ->and($second->number)->toBe('PROP-'.now()->year.'-00002')
        ->and($first->subtotal_cents)->toBe(240_000)
        ->and($first->discount_cents)->toBe(5_000)
        ->and($first->total_cents)->toBe(235_000)
        ->and($first->status)->toBe(ProposalStatus::Draft)
        ->and($first->items)->toHaveCount(2)
        ->and($first->statusChanges)->toHaveCount(1);
});

it('segue o ciclo rascunho → enviada → aprovada e registra o histórico', function () {
    $proposal = app(SaveProposal::class)->create(proposalPayload($this->customer, $this->service));
    $action = app(ChangeProposalStatus::class);

    $action->handle($proposal, ProposalStatus::Sent);
    $action->handle($proposal, ProposalStatus::Approved);

    expect($proposal->fresh())
        ->status->toBe(ProposalStatus::Approved)
        ->approved_by->toBe($this->user->id)
        ->and($proposal->statusChanges()->count())->toBe(3);
});

it('impede transições inválidas de status', function () {
    $proposal = Proposal::factory()->create();

    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Approved);
})->throws(BusinessRuleException::class, 'Não é possível alterar');

it('exige motivo para recusar', function () {
    $proposal = Proposal::factory()->status(ProposalStatus::Sent)->create();

    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Rejected);
})->throws(BusinessRuleException::class, 'motivo');

it('não permite editar proposta que não está em rascunho', function () {
    $proposal = Proposal::factory()->status(ProposalStatus::Sent)->create(['customer_id' => $this->customer->id]);

    app(SaveProposal::class)->update($proposal, proposalPayload($this->customer, $this->service));
})->throws(BusinessRuleException::class, 'rascunho');

it('converte proposta aprovada em contrato sem duplicar dados e apenas uma vez', function () {
    $proposal = app(SaveProposal::class)->create(proposalPayload($this->customer, $this->service));
    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Sent);
    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Approved);

    $contract = app(ConvertProposalToContract::class)->handle($proposal->fresh(), ['billing_day' => 15]);

    expect($contract->proposal_id)->toBe($proposal->id)
        ->and($contract->customer_id)->toBe($this->customer->id)
        ->and($contract->amount_cents)->toBe(235_000)
        ->and($contract->billing_day)->toBe(15)
        ->and($contract->status)->toBe(ContractStatus::Draft)
        ->and($contract->items)->toHaveCount(2)
        ->and($contract->number)->toBe('CTR-'.now()->year.'-00001');

    expect(fn () => app(ConvertProposalToContract::class)->handle($proposal->fresh()))
        ->toThrow(BusinessRuleException::class, 'já foi convertida');

    expect(Contract::count())->toBe(1);
});

it('não converte proposta que não foi aprovada', function () {
    $proposal = Proposal::factory()->status(ProposalStatus::Sent)->create();

    app(ConvertProposalToContract::class)->handle($proposal);
})->throws(BusinessRuleException::class, 'aprovadas');

it('aprovar proposta vinculada marca a oportunidade como ganha no CRM e notifica o responsável', function () {
    Notification::fake();

    $opportunity = Opportunity::factory()->create(['customer_id' => $this->customer->id]);
    $owner = createUserWithRole(currentCompany(), 'sales');
    $proposal = Proposal::factory()->status(ProposalStatus::Sent)->create([
        'customer_id' => $this->customer->id,
        'opportunity_id' => $opportunity->id,
        'owner_id' => $owner->id,
    ]);

    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Approved);

    expect($opportunity->fresh()->status)->toBe(OpportunityStatus::Won)
        ->and($opportunity->fresh()->stage->is_won)->toBeTrue();

    Notification::assertSentTo($owner, ProposalStatusChangedNotification::class);
});

it('expira propostas enviadas com validade vencida', function () {
    $expired = Proposal::factory()->status(ProposalStatus::Sent)->create(['valid_until' => today()->subDay()]);
    $valid = Proposal::factory()->status(ProposalStatus::Sent)->create(['valid_until' => today()->addDay()]);

    expect(app(ExpireProposals::class)->forCurrentCompany())->toBe(1)
        ->and($expired->fresh()->status)->toBe(ProposalStatus::Expired)
        ->and($valid->fresh()->status)->toBe(ProposalStatus::Sent);
});

it('fluxo completo: proposta aprovada vira contrato ativo que gera cobrança', function () {
    $proposal = app(SaveProposal::class)->create(proposalPayload($this->customer, $this->service));
    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Sent);
    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Approved);

    $contract = app(ConvertProposalToContract::class)->handle($proposal->fresh(), [
        'start_date' => today()->toDateString(),
        'billing_day' => min(28, today()->addDays(3)->day),
        'activate' => true,
    ]);

    expect($contract->fresh()->status)->toBe(ContractStatus::Active)
        ->and($contract->fresh()->next_billing_date)->not->toBeNull();

    $this->artisan('erp:daily-routines', ['--sync' => true, '--company' => currentCompany()->id])->assertSuccessful();

    expect(Receivable::where('contract_id', $contract->id)->count())->toBeGreaterThanOrEqual(1);
});

it('rateia o desconto geral da proposta nos itens do contrato, preservando o valor ao editar', function () {
    $proposal = app(SaveProposal::class)->create(proposalPayload($this->customer, $this->service));
    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Sent);
    app(ChangeProposalStatus::class)->handle($proposal, ProposalStatus::Approved);

    $contract = app(ConvertProposalToContract::class)->handle($proposal->fresh());

    expect((int) $contract->items()->sum('total_cents'))->toBe(235_000)
        ->and((int) $contract->items()->sum('discount_cents'))->toBe(15_000);

    $items = $contract->items()->orderBy('position')->get()->map(fn ($item) => [
        'catalog_item_id' => $item->catalog_item_id,
        'description' => $item->description,
        'quantity' => (string) $item->quantity,
        'unit' => $item->unit,
        'unit_price_cents' => $item->unit_price_cents,
        'discount_cents' => $item->discount_cents,
    ])->all();

    $updated = app(SaveContract::class)->update($contract->fresh(), ['title' => 'Implantação (revisado)', 'items' => $items]);

    expect($updated->amount_cents)->toBe(235_000);
});
