<?php

namespace Database\Factories;

use App\Models\Role;
use Database\Factories\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    use ResolvesCompany;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Perfil '.fake()->unique()->word();

        return [
            'company_id' => $this->companyId(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }
}
