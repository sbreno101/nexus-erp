<?php

namespace Database\Factories;

use App\Enums\BillingFrequency;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 */
class RecurringExpenseFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create(['company_id' => $attributes['company_id']])->id,
            'description' => fake()->randomElement(['Aluguel do escritório', 'Plano de internet', 'ERP contábil', 'Plano de saúde']),
            'amount_cents' => fake()->numberBetween(5, 50) * 10000,
            'frequency' => BillingFrequency::Monthly,
            'due_day' => 5,
            'start_date' => today()->startOfMonth(),
            'next_due_date' => today()->addDays(3),
            'is_active' => true,
        ];
    }
}
