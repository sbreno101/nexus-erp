<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Models\Customer;
use App\Models\Proposal;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Proposta com um item de serviço (totais coerentes com o item).
 *
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(10, 200) * 1000;

        return [
            'company_id' => $this->companyId(),
            'number' => 'PROP-'.now()->year.'-'.fake()->unique()->numerify('9####'),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'title' => 'Proposta de '.fake()->words(3, true),
            'status' => ProposalStatus::Draft,
            'issue_date' => today(),
            'valid_until' => today()->addDays(15),
            'subtotal_cents' => $unitPrice,
            'discount_cents' => 0,
            'total_cents' => $unitPrice,
            'payment_terms' => 'Pagamento mensal via boleto, vencimento todo dia 10.',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Proposal $proposal): void {
            if (! $proposal->items()->exists()) {
                $proposal->items()->create([
                    'description' => 'Serviço mensal de suporte',
                    'quantity' => '1',
                    'unit' => 'MÊS',
                    'unit_price_cents' => $proposal->subtotal_cents,
                    'discount_cents' => 0,
                    'total_cents' => $proposal->subtotal_cents,
                    'position' => 0,
                ]);
            }
        });
    }

    public function status(ProposalStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'sent_at' => $status === ProposalStatus::Draft ? null : now(),
            'approved_at' => $status === ProposalStatus::Approved ? now() : null,
        ]);
    }
}
