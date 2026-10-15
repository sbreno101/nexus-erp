<?php

namespace Database\Factories;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * O saldo atual começa igual ao saldo inicial (como faz a Action
 * CreateFinancialAccount), mantendo o extrato consistente.
 *
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $opening = fake()->numberBetween(10, 100) * 100000;

        return [
            'company_id' => $this->companyId(),
            'name' => 'Conta '.fake()->unique()->word(),
            'type' => FinancialAccountType::Checking,
            'bank_name' => fake()->randomElement(['Banco do Brasil', 'Itaú', 'Bradesco', 'Santander', 'Caixa', 'Nubank']),
            'bank_code' => fake()->numerify('###'),
            'agency' => fake()->numerify('####'),
            'account_number' => fake()->numerify('#####-#'),
            'opening_balance_cents' => $opening,
            'current_balance_cents' => $opening,
            'opening_date' => today()->subYear(),
            'is_active' => true,
        ];
    }

    public function withBalance(int $cents): static
    {
        return $this->state(fn () => ['opening_balance_cents' => $cents, 'current_balance_cents' => $cents]);
    }
}
