<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $companyId = app(CurrentCompany::class)->id() ?? $this->current_company_id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'job_title' => $this->job_title,
            'is_super_admin' => $this->is_super_admin,
            'current_company_id' => $companyId,
            'companies' => $this->accessibleCompanies()->map(fn ($company) => ['id' => $company->id, 'name' => $company->display_name])->values(),
            'permissions' => $this->isSuperAdmin() ? ['*'] : ($companyId ? $this->permissionsFor($companyId) : []),
        ];
    }
}
