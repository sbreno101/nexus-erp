<?php

namespace Database\Factories;

use App\Enums\InteractionType;
use App\Models\Interaction;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interaction>
 */
class InteractionFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'type' => fake()->randomElement(InteractionType::cases()),
            'subject' => fake()->randomElement(['Apresentação inicial', 'Alinhamento de escopo', 'Follow-up', 'Negociação de valores']),
            'description' => fake()->paragraph(),
            'occurred_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];
    }
}
