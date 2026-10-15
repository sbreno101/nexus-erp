<?php

namespace App\Actions\Parties;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Cria/atualiza um cliente ou fornecedor junto com endereços e contatos,
 * tudo em uma única transação (ou grava tudo, ou nada).
 */
class SaveBusinessParty
{
    public function __construct(private SyncContactDetails $syncContactDetails) {}

    /**
     * @template TParty of Customer|Supplier
     *
     * @param  TParty  $party
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $addresses
     * @param  list<array<string, mixed>>  $contacts
     * @return TParty
     */
    public function handle(Customer|Supplier $party, array $data, array $addresses, array $contacts): Customer|Supplier
    {
        return DB::transaction(function () use ($party, $data, $addresses, $contacts) {
            $party->fill($data)->save();
            $this->syncContactDetails->handle($party, $addresses, $contacts);

            return $party;
        });
    }
}
