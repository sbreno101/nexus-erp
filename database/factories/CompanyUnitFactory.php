<?php

namespace Database\Factories;

use App\Models\CompanyUnit;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyUnit>
 */
class CompanyUnitFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'name' => 'Unidade '.fake()->city(),
            'code' => strtoupper(fake()->unique()->bothify('UN-###')),
            'city' => fake()->city(),
            'state' => 'SP',
            'is_active' => true,
        ];
    }
}
