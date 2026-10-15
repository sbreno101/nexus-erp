<?php

namespace Database\Factories;

use App\Enums\OpportunityStatus;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\Scopes\CompanyScope;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'title' => 'Projeto '.fake()->words(2, true),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'pipeline_stage_id' => fn (array $attributes) => $this->stageFor($attributes['company_id'])->id,
            'estimated_value_cents' => fake()->numberBetween(20, 500) * 10000,
            'probability' => 25,
            'expected_close_date' => fake()->dateTimeBetween('now', '+90 days'),
            'status' => OpportunityStatus::Open,
            'position' => 0,
        ];
    }

    private function stageFor(int $companyId): PipelineStage
    {
        return PipelineStage::query()->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)->where('is_won', false)->where('is_lost', false)->orderBy('position')->first()
            ?? PipelineStage::query()->forceCreate(['company_id' => $companyId, 'name' => 'Prospecção', 'position' => 0, 'probability' => 10]);
    }
}
