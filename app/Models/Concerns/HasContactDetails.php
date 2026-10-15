<?php

namespace App\Models\Concerns;

use App\Models\Address;
use App\Models\ContactPerson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Endereços e contatos responsáveis (clientes e fornecedores).
 *
 * @mixin Model
 */
trait HasContactDetails
{
    /**
     * @return MorphMany<Address, $this>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable')->orderByDesc('is_primary')->orderBy('id');
    }

    /**
     * Endereço principal (morphOne: retorna um único registro). A Action
     * SyncContactDetails garante que sempre exista no máximo um principal.
     *
     * @return MorphOne<Address, $this>
     */
    public function primaryAddress(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')->where('is_primary', true);
    }

    /**
     * @return MorphMany<ContactPerson, $this>
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(ContactPerson::class, 'contactable')->orderByDesc('is_primary')->orderBy('name');
    }
}
