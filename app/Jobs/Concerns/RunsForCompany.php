<?php

namespace App\Jobs\Concerns;

use App\Models\Company;
use App\Support\Tenancy\CurrentCompany;

/**
 * Jobs multiempresa recebem a empresa no construtor e executam a lógica
 * "dentro" dela (CurrentCompany::run), para que o CompanyScope funcione
 * exatamente como em uma requisição web.
 *
 * uniqueId(): com ShouldBeUnique, impede que o mesmo job da mesma empresa
 * seja enfileirado duas vezes enquanto o primeiro não terminou.
 */
trait RunsForCompany
{
    public function uniqueId(): string
    {
        return (string) $this->company->id;
    }

    /**
     * @template TReturn
     *
     * @param  callable(Company): TReturn  $callback
     * @return TReturn
     */
    protected function withinCompany(callable $callback): mixed
    {
        return app(CurrentCompany::class)->run($this->company, $callback);
    }
}
