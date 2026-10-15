<?php

namespace App\Support\Billing;

use App\Enums\BillingFrequency;
use Carbon\CarbonImmutable;

/**
 * Cálculo de datas de cobrança recorrente.
 *
 * O dia de cobrança é limitado a 1..28 (validação e CHECK no banco) para que
 * exista em todos os meses — inclusive fevereiro —, evitando ambiguidades
 * como "dia 31 de fevereiro".
 */
class BillingSchedule
{
    /**
     * Próximo vencimento a partir de um vencimento atual.
     */
    public function advance(CarbonImmutable $current, BillingFrequency $frequency, int $billingDay): CarbonImmutable
    {
        return $current->startOfMonth()->addMonthsNoOverflow($frequency->months())->setDay($billingDay);
    }

    /**
     * Competência (mês de referência) de uma cobrança: o 1º dia do mês do vencimento.
     */
    public function competenceFor(CarbonImmutable $dueDate): CarbonImmutable
    {
        return $dueDate->startOfMonth();
    }
}
