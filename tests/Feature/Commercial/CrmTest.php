<?php

use App\Actions\Crm\ConvertLeadToCustomer;
use App\Actions\Crm\MoveOpportunity;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStatus;
use App\Enums\PersonType;
use App\Exceptions\BusinessRuleException;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Support\BrazilianDocument;

beforeEach(fn () => actingAsRole('sales'));

it('converte lead em cliente preservando a origem', function () {
    $lead = Lead::factory()->create();
    $opportunity = Opportunity::factory()->create(['lead_id' => $lead->id, 'customer_id' => null]);

    $customer = app(ConvertLeadToCustomer::class)->handle($lead, [
        'type' => PersonType::Company->value,
        'name' => 'Empresa do Lead Ltda',
        'document' => BrazilianDocument::generateCnpj(),
        'email' => 'contato@lead.com.br',
    ]);

    expect($lead->fresh())
        ->status->toBe(LeadStatus::Converted)
        ->customer_id->toBe($customer->id)
        ->and($opportunity->fresh()->customer_id)->toBe($customer->id)
        ->and($customer->originLead->id)->toBe($lead->id);

    expect(fn () => app(ConvertLeadToCustomer::class)->handle($lead->fresh(), ['type' => 'company', 'name' => 'X', 'document' => BrazilianDocument::generateCnpj()]))
        ->toThrow(BusinessRuleException::class);
});

it('move oportunidades no funil persistindo etapa, posição e status', function () {
    $stages = PipelineStage::query()->ordered()->get();
    $negotiation = $stages->firstWhere('name', 'Negociação');
    $won = $stages->firstWhere('is_won', true);
    $lost = $stages->firstWhere('is_lost', true);

    $first = Opportunity::factory()->create(['pipeline_stage_id' => $negotiation->id, 'position' => 0]);
    $moving = Opportunity::factory()->create();

    app(MoveOpportunity::class)->handle($moving, $negotiation, position: 0);

    expect($moving->fresh())
        ->pipeline_stage_id->toBe($negotiation->id)
        ->position->toBe(0)
        ->probability->toBe($negotiation->probability)
        ->and($first->fresh()->position)->toBe(1);

    app(MoveOpportunity::class)->handle($moving->fresh(), $won);
    expect($moving->fresh())->status->toBe(OpportunityStatus::Won)->closed_at->not->toBeNull();

    app(MoveOpportunity::class)->handle($moving->fresh(), $lost, lostReason: 'Preço');
    expect($moving->fresh())->status->toBe(OpportunityStatus::Lost)->lost_reason->toBe('Preço');

    app(MoveOpportunity::class)->handle($moving->fresh(), $negotiation);
    expect($moving->fresh())->status->toBe(OpportunityStatus::Open)->closed_at->toBeNull();
});
