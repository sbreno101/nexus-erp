<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->companyId(),
            'number' => 'CHM-'.now()->year.'-'.fake()->unique()->numerify('9####'),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'subject' => fake()->randomElement(['Erro ao emitir relatório', 'Dúvida sobre fatura', 'Lentidão no sistema', 'Solicitação de treinamento', 'Acesso bloqueado']),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(Priority::cases()),
            'status' => TicketStatus::Open,
            'channel' => fake()->randomElement(TicketChannel::cases()),
        ];
    }
}
