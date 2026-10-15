<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\Payable;
use App\Models\Supplier;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payable>
 */
class PayableFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dueDate = fake()->dateTimeBetween('-20 days', '+30 days');

        return [
            'company_id' => $this->companyId(),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create(['company_id' => $attributes['company_id']])->id,
            'description' => fake()->randomElement(['Aluguel do escritório', 'Licenças de software', 'Serviço de contabilidade', 'Internet e telefonia', 'Material de escritório']),
            'amount_cents' => fake()->numberBetween(2, 30) * 10000,
            'paid_cents' => 0,
            'issue_date' => today()->subDays(15),
            'due_date' => $dueDate,
            'competence' => (clone $dueDate)->modify('first day of this month'),
            'status' => BillStatus::Open,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['due_date' => today()->subDays(5), 'competence' => today()->subDays(5)->startOfMonth()]);
    }
}
