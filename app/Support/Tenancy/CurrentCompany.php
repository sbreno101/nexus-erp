<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use RuntimeException;

/**
 * Guarda a "empresa ativa" do contexto atual (requisição HTTP, job ou comando).
 *
 * É registrada no container como "scoped" (AppServiceProvider): existe uma
 * única instância por requisição/job, descartada ao final. Quem define a
 * empresa ativa é o middleware SetCurrentCompany (web e API) ou, em jobs e
 * comandos, o método run().
 *
 * O CompanyScope consulta esta classe para filtrar automaticamente todas as
 * consultas dos models multiempresa.
 */
final class CurrentCompany
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function check(): bool
    {
        return $this->company !== null;
    }

    /**
     * @throws RuntimeException quando não há empresa ativa no contexto.
     */
    public function getOrFail(): Company
    {
        return $this->company ?? throw new RuntimeException('Nenhuma empresa ativa definida para esta operação.');
    }

    /**
     * Executa um trecho de código "dentro" de uma empresa e restaura o contexto
     * anterior ao final. Usado por jobs e comandos agendados que percorrem
     * várias empresas.
     *
     * @template TReturn
     *
     * @param  callable(Company): TReturn  $callback
     * @return TReturn
     */
    public function run(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback($company);
        } finally {
            $this->company = $previous;
        }
    }
}
