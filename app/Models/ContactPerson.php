<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Contato responsável (pessoa) de um cliente ou fornecedor.
 */
#[Fillable(['name', 'position', 'email', 'phone', 'is_primary'])]
class ContactPerson extends Model
{
    use BelongsToCompany;

    protected $table = 'contact_people';

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
