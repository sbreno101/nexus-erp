<?php

namespace App\Http\Resources\V1\Concerns;

use BackedEnum;
use DateTimeInterface;

/**
 * Convenções de formato da API:
 *  - Dinheiro: objeto {"cents": 150000, "formatted": "R$ 1.500,00"} (inteiro em centavos + texto).
 *  - Enums: objeto {"value": "...", "label": "..."}.
 *  - Datas: "AAAA-MM-DD"; data/hora: ISO 8601.
 */
trait FormatsValues
{
    /**
     * @return array{value: string, label: string}|null
     */
    protected function enum(?BackedEnum $enum): ?array
    {
        return $enum === null ? null : ['value' => $enum->value, 'label' => method_exists($enum, 'label') ? $enum->label() : (string) $enum->value];
    }

    /**
     * @return array{cents: int, formatted: string}
     */
    protected function money(?int $cents): array
    {
        return ['cents' => (int) $cents, 'formatted' => money($cents)];
    }

    protected function date(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    protected function dateTime(?DateTimeInterface $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }
}
