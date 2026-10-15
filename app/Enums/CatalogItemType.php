<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Tipo de item do catálogo.
 */
enum CatalogItemType: string
{
    use EnumHelpers;

    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Produto',
            self::Service => 'Serviço',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Product => 'orange',
            self::Service => 'indigo',
        };
    }
}
