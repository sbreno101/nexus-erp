<?php

namespace Database\Factories;

use App\Enums\PersonType;
use App\Enums\RecordStatus;
use App\Models\Supplier;
use App\Support\BrazilianDocument;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
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
            'name' => $company.' S.A.',
            'trade_name' => $company,
            'document' => BrazilianDocument::generateCnpj(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('11########'),
            'status' => RecordStatus::Active,
        ];
    }
}
