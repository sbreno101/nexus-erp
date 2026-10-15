<?php

namespace App\Policies;

class ServiceOrderPolicy extends TenantResourcePolicy
{
    protected string $permission = 'service_orders';
}
