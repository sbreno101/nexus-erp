<?php

namespace App\Policies;

class TicketPolicy extends TenantResourcePolicy
{
    protected string $permission = 'tickets';
}
