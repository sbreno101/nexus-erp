<?php

namespace App\Livewire\Finance;

use App\Models\Payable;
use Livewire\Attributes\Title;

#[Title('Contas a pagar')]
class PayableIndex extends BillIndex
{
    protected function modelClass(): string
    {
        return Payable::class;
    }

    protected function kind(): string
    {
        return 'payable';
    }

    protected function partyRelation(): string
    {
        return 'supplier';
    }
}
