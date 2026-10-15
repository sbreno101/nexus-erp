<?php

namespace App\Actions\Parties;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza endereços e contatos (relações polimórficas) de clientes e
 * fornecedores a partir das listas enviadas pelo formulário.
 *
 * Estratégia "substituir a coleção": linhas com id são atualizadas, linhas
 * sem id são criadas e as que sumiram do formulário são removidas.
 */
class SyncContactDetails
{
    /**
     * @param  list<array<string, mixed>>  $addresses
     * @param  list<array<string, mixed>>  $contacts
     */
    public function handle(Customer|Supplier $party, array $addresses, array $contacts): void
    {
        DB::transaction(function () use ($party, $addresses, $contacts): void {
            $this->syncRelation($party->addresses(), $this->ensureSinglePrimary($addresses), [
                'label', 'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state', 'is_primary',
            ]);

            $this->syncRelation($party->contacts(), $this->ensureSinglePrimary($contacts), [
                'name', 'position', 'email', 'phone', 'is_primary',
            ]);
        });
    }

    /**
     * @param  MorphMany<Model, Model>  $relation
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $fields
     */
    private function syncRelation($relation, array $rows, array $fields): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $attributes = array_intersect_key($row, array_flip($fields));

            if (! empty($row['id']) && $existing = (clone $relation)->whereKey($row['id'])->first()) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $relation->create($attributes)->id;
        }

        (clone $relation)->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Garante no máximo um item principal (o primeiro marcado, ou o primeiro da lista).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function ensureSinglePrimary(array $rows): array
    {
        $rows = array_values($rows);
        $primaryIndex = null;

        foreach ($rows as $index => $row) {
            if (! empty($row['is_primary']) && $primaryIndex === null) {
                $primaryIndex = $index;
            }
        }

        $primaryIndex ??= $rows === [] ? null : 0;

        foreach ($rows as $index => $row) {
            $rows[$index]['is_primary'] = $index === $primaryIndex;
        }

        return $rows;
    }
}
