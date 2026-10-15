<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;

/**
 * Ativa/desativa o acesso de um usuário.
 *
 * Dois níveis:
 *  - Vínculo com a empresa (company_user.is_active): o administrador da
 *    empresa bloqueia o acesso àquela empresa, sem afetar outras empresas
 *    em que a pessoa trabalhe.
 *  - Conta global (users.is_active): somente o superadministrador. Bloqueia
 *    o login em qualquer empresa.
 *
 * Ao desativar a conta, as sessões abertas e os tokens de API do usuário
 * são revogados para que o bloqueio tenha efeito imediato. Ao desativar só
 * o vínculo, a revogação acontece apenas se não restar outra empresa ativa:
 * o acesso à empresa bloqueada já é negado a cada requisição (o middleware
 * SetCurrentCompany só aceita vínculos ativos), sem derrubar o trabalho da
 * pessoa nas demais empresas.
 */
class ToggleUserAccess
{
    public function __construct(
        private CurrentCompany $currentCompany,
        private AuditLogger $audit,
    ) {}

    public function forCompany(User $user, bool $active): void
    {
        DB::transaction(function () use ($user, $active): void {
            $membership = $user->memberships()->where('company_id', $this->currentCompany->id())->firstOrFail();
            $membership->update(['is_active' => $active]);

            if (! $active && ! $user->is_super_admin && ! $user->memberships()->where('is_active', true)->exists()) {
                $this->revokeAccess($user);
            }

            $this->audit->log($active ? 'user.membership_activated' : 'user.membership_deactivated', $user);
        });
    }

    public function globally(User $user, bool $active): void
    {
        DB::transaction(function () use ($user, $active): void {
            $user->forceFill([
                'is_active' => $active,
                'deactivated_at' => $active ? null : now(),
            ])->save();

            if (! $active) {
                $this->revokeAccess($user);
            }
        });
    }

    private function revokeAccess(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
    }
}
