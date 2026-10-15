<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy extends TenantResourcePolicy
{
    protected string $permission = 'leads';

    /**
     * Converter exige permissão específica e que o lead ainda não tenha sido convertido.
     */
    public function convert(User $user, Lead $lead): bool
    {
        return ! $lead->isConverted() && $this->allows($user, $lead, 'leads.convert');
    }
}
