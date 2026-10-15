<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ServiceOrderStatus;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'number' => 'OS-'.now()->year.'-'.fake()->unique()->numerify('9####'),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'title' => fake()->randomElement(['Manutenção preventiva', 'Instalação de rede', 'Configuração de servidor', 'Visita técnica', 'Migração de dados']),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(Priority::cases()),
            'status' => ServiceOrderStatus::Open,
            'due_date' => fake()->dateTimeBetween('-5 days', '+20 days'),
        ];
    }
}
