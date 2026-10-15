<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Enums\PersonType;
use App\Models\Customer;
use App\Support\BrazilianDocument;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'company_id' => $this->companyId(),
            'type' => PersonType::Company,
            'name' => $company.' Ltda',
            'trade_name' => $company,
            'document' => BrazilianDocument::generateCnpj(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('11########'),
            'mobile' => fake()->numerify('119########'),
            'status' => CustomerStatus::Active,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => [
            'type' => PersonType::Individual,
            'name' => fake()->name(),
            'trade_name' => null,
            'document' => BrazilianDocument::generateCpf(),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CustomerStatus::Inactive]);
    }
}
