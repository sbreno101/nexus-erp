<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Support\BrazilianDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'legal_name' => $name.' Ltda',
            'trade_name' => $name,
            'cnpj' => BrazilianDocument::generateCnpj(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('11########'),
            'zip_code' => fake()->numerify('0#######'),
            'street' => fake()->streetName(),
            'number' => (string) fake()->buildingNumber(),
            'district' => 'Centro',
            'city' => fake()->city(),
            'state' => fake()->randomElement(['SP', 'RJ', 'MG', 'PR', 'SC', 'RS']),
            'status' => CompanyStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CompanyStatus::Inactive]);
    }
}
