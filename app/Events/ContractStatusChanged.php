<?php

namespace App\Events;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * O status de um contrato mudou (ativação, suspensão, cancelamento...).
 */
class ContractStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Contract $contract,
        public ?ContractStatus $from,
        public ContractStatus $to,
    ) {}
}
