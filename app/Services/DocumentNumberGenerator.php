<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gera números sequenciais legíveis por empresa e ano: "PROP-2026-00001".
 *
 * Por que não usar o ID do banco? Porque o ID é global (compartilhado entre
 * empresas) e tem "buracos". Clientes esperam numeração contínua por empresa.
 *
 * Concorrência: a linha do contador é travada com SELECT ... FOR UPDATE
 * (lockForUpdate) dentro de uma transação. Se dois usuários criarem
 * propostas no mesmo instante, o segundo espera o primeiro terminar e
 * recebe o número seguinte — nunca o mesmo número.
 */
class DocumentNumberGenerator
{
    public const string PROPOSAL = 'proposal';

    public const string CONTRACT = 'contract';

    public const string SERVICE_ORDER = 'service_order';

    public const string TICKET = 'ticket';

    public function __construct(private CurrentCompany $currentCompany) {}

    public function next(string $type, ?Company $company = null, ?CarbonInterface $date = null): string
    {
        $company ??= $this->currentCompany->getOrFail();
        $year = ($date ?? now())->year;
        $prefix = $this->prefixFor($company, $type);

        return DB::transaction(function () use ($company, $type, $year, $prefix): string {
            // Cria a linha do contador se ainda não existir (INSERT ... ON CONFLICT DO NOTHING).
            DocumentSequence::query()->insertOrIgnore([
                'company_id' => $company->id,
                'type' => $type,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()
                ->where('company_id', $company->id)
                ->where('type', $type)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return sprintf('%s-%d-%05d', $prefix, $year, $sequence->last_number);
        });
    }

    private function prefixFor(Company $company, string $type): string
    {
        $settings = $company->settingsOrDefault();

        return match ($type) {
            self::PROPOSAL => $settings->proposal_prefix,
            self::CONTRACT => $settings->contract_prefix,
            self::SERVICE_ORDER => $settings->service_order_prefix,
            self::TICKET => $settings->ticket_prefix,
            default => throw new InvalidArgumentException("Tipo de documento desconhecido: {$type}"),
        };
    }
}
