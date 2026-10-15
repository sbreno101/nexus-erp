<?php

namespace App\Policies;

class OpportunityPolicy extends TenantResourcePolicy
{
    protected string $permission = 'opportunities';
}
