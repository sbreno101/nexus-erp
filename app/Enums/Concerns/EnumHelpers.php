<?php

namespace App\Enums\Concerns;

/**
 * Métodos utilitários compartilhados pelos Enums da aplicação.
 *
 * Cada Enum que usa este trait deve implementar label(): string.
 */
trait EnumHelpers
{
    /**
     * Lista "valor => rótulo", ideal para <select> em formulários.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Lista apenas os valores, útil para a regra de validação "in".
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Cor usada pelo componente de badge. Enums podem sobrescrever.
     */
    public function color(): string
    {
        return 'gray';
    }
}
