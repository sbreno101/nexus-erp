<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('119########'),
            'status' => LeadStatus::New,
            'estimated_value_cents' => fake()->numberBetween(50, 2000) * 1000,
            'next_contact_at' => fake()->dateTimeBetween('now', '+20 days'),
        ];
    }
}
