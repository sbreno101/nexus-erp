<?php

namespace Database\Factories;

use App\Enums\CatalogItemType;
use App\Models\CatalogItem;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'type' => CatalogItemType::Service,
            'code' => strtoupper(fake()->unique()->bothify('SRV-####')),
            'name' => 'Serviço de '.fake()->words(2, true),
            'description' => fake()->sentence(),
            'unit' => 'H',
            'price_cents' => fake()->numberBetween(80, 400) * 100,
            'is_active' => true,
        ];
    }

    public function product(): static
    {
        return $this->state(fn () => [
            'type' => CatalogItemType::Product,
            'code' => strtoupper(fake()->unique()->bothify('PRD-####')),
            'name' => 'Licença '.fake()->word(),
            'unit' => 'UN',
        ]);
    }
}
