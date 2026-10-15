<?php

namespace App\Events;

use App\Models\ServiceOrder;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Uma ordem de serviço ou um chamado foi atribuído a um responsável.
 */
class WorkItemAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ServiceOrder|Ticket $item,
        public User $assignee,
        public ?User $assignedBy = null,
    ) {}
}
