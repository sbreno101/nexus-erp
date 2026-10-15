<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\Customer;
use App\Models\Receivable;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receivable>
 */
class ReceivableFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dueDate = fake()->dateTimeBetween('-30 days', '+30 days');

        return [
            'company_id' => $this->companyId(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'description' => 'Serviços prestados',
            'amount_cents' => fake()->numberBetween(5, 50) * 10000,
            'paid_cents' => 0,
            'issue_date' => today()->subDays(10),
            'due_date' => $dueDate,
            'competence' => (clone $dueDate)->modify('first day of this month'),
            'status' => BillStatus::Open,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['due_date' => today()->subDays(10), 'competence' => today()->subDays(10)->startOfMonth()]);
    }

    public function dueIn(int $days): static
    {
        return $this->state(fn () => ['due_date' => today()->addDays($days), 'competence' => today()->addDays($days)->startOfMonth()]);
    }
}
