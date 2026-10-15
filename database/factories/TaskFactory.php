<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Models\Task;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'title' => fake()->randomElement(['Retornar ligação', 'Enviar proposta revisada', 'Agendar reunião de apresentação', 'Confirmar recebimento do contrato']),
            'description' => fake()->sentence(),
            'priority' => fake()->randomElement(Priority::cases()),
            'due_at' => fake()->dateTimeBetween('-3 days', '+14 days'),
        ];
    }
}
