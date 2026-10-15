<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Natureza de uma categoria financeira.
 */
enum FinancialCategoryType: string
{
    use EnumHelpers;

    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Receita',
            self::Expense => 'Despesa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Income => 'green',
            self::Expense => 'red',
        };
    }
}
