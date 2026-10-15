<?php

namespace Database\Factories;

use App\Enums\BillingFrequency;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Customer;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'number' => 'CTR-'.now()->year.'-'.fake()->unique()->numerify('9####'),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'title' => 'Contrato de '.fake()->words(3, true),
            'status' => ContractStatus::Draft,
            'start_date' => today()->startOfMonth(),
            'billing_frequency' => BillingFrequency::Monthly,
            'billing_day' => 10,
            'amount_cents' => fake()->numberBetween(10, 100) * 10000,
            'auto_renew' => false,
            'renewal_months' => 12,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Contract $contract): void {
            if (! $contract->items()->exists()) {
                $contract->items()->create([
                    'description' => 'Mensalidade do serviço contratado',
                    'quantity' => '1',
                    'unit' => 'MÊS',
                    'unit_price_cents' => $contract->amount_cents,
                    'discount_cents' => 0,
                    'total_cents' => $contract->amount_cents,
                    'position' => 0,
                ]);
            }
        });
    }

    /**
     * Contrato ativo com próxima cobrança definida.
     */
    public function active(?string $nextBillingDate = null): static
    {
        return $this->state(fn () => [
            'status' => ContractStatus::Active,
            'activated_at' => now(),
            'signed_at' => today()->startOfMonth(),
            'next_billing_date' => $nextBillingDate ?? today()->addDays(5)->toDateString(),
        ]);
    }
}
