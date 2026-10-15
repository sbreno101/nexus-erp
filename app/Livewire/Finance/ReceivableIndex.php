<?php

namespace App\Livewire\Finance;

use App\Models\Receivable;
use Livewire\Attributes\Title;

#[Title('Contas a receber')]
class ReceivableIndex extends BillIndex
{
    protected function modelClass(): string
    {
        return Receivable::class;
    }

    protected function kind(): string
    {
        return 'receivable';
    }

    protected function partyRelation(): string
    {
        return 'customer';
    }
}
