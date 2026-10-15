<?php

namespace Database\Factories\Concerns;

use App\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Closure;

/**
 * Factories de models multiempresa usam a empresa ativa, quando houver;
 * caso contrário, criam uma empresa nova. Também é possível informar
 * explicitamente: Customer::factory()->for($company)->create().
 */
trait ResolvesCompany
{
    protected function companyId(): Closure
    {
        return fn (): int => app(CurrentCompany::class)->id() ?? Company::factory()->create()->id;
    }
}
