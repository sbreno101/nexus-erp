<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Pessoa física (CPF) ou jurídica (CNPJ).
 */
enum PersonType: string
{
    use EnumHelpers;

    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Pessoa física',
            self::Company => 'Pessoa jurídica',
        };
    }

    public function documentLabel(): string
    {
        return match ($this) {
            self::Individual => 'CPF',
            self::Company => 'CNPJ',
        };
    }

    /**
     * Tipo de documento aceito pela regra App\Rules\CpfCnpj.
     *
     * @return 'cpf'|'cnpj'
     */
    public function documentRule(): string
    {
        return match ($this) {
            self::Individual => 'cpf',
            self::Company => 'cnpj',
        };
    }
}
