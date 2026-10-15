<?php

namespace App\Policies;

class CatalogItemPolicy extends TenantResourcePolicy
{
    protected string $permission = 'catalog';
}
