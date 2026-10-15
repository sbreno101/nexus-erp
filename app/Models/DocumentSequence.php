<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador de numeração sequencial por empresa, tipo de documento e ano.
 *
 * Não usa o CompanyScope porque é manipulado apenas pelo
 * DocumentNumberGenerator, sempre com company_id explícito.
 */
#[Fillable(['company_id', 'type', 'year', 'last_number'])]
class DocumentSequence extends Model
{
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
