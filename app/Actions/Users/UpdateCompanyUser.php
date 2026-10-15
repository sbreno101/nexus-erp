<?php

namespace App\Actions\Users;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Atualiza dados cadastrais do usuário e seu vínculo (perfil e unidade)
 * com a empresa ativa.
 */
class UpdateCompanyUser
{
    public function __construct(private CurrentCompany $currentCompany) {}

    /**
     * @param  array{name: string, phone?: string|null, job_title?: string|null, role_id: int, company_unit_id?: int|null}  $data
     */
    public function handle(User $user, array $data): User
    {
        $companyId = $this->currentCompany->id();

        return DB::transaction(function () use ($user, $data, $companyId): User {
            $membership = $user->memberships()->where('company_id', $companyId)->firstOrFail();

            // Evita que um administrador remova o próprio acesso administrativo por engano.
            if ($user->is(Auth::user())
                && $membership->role_id !== (int) $data['role_id']
                && Role::query()->whereKey($membership->role_id)->value('slug') === Role::COMPANY_ADMIN) {
                throw new BusinessRuleException('Você não pode alterar o seu próprio perfil de administrador.');
            }

            $user->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
            ]);

            $membership->update([
                'role_id' => $data['role_id'],
                'company_unit_id' => $data['company_unit_id'] ?? null,
            ]);

            $user->flushPermissionCache();

            return $user;
        });
    }
}
