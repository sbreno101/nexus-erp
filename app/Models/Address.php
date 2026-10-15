<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Endereço polimórfico (pertence a um cliente ou fornecedor).
 */
#[Fillable(['label', 'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state', 'is_primary'])]
class Address extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Endereço em uma linha: "Rua X, 100 - Centro, São Paulo/SP".
     */
    public function oneLine(): string
    {
        $street = trim($this->street.($this->number ? ', '.$this->number : ''));
        $district = $this->district ? ' - '.$this->district : '';

        return "{$street}{$district}, {$this->city}/{$this->state}";
    }
}
