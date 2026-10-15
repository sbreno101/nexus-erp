<?php

namespace App\Http\Requests\Settings\Concerns;

use App\Models\Role;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Regras do vínculo usuário <-> empresa (perfil e unidade), compartilhadas
 * pelo cadastro e pela edição de usuários.
 *
 * Proteção contra escalada de privilégio: quem pode cadastrar usuários mas
 * NÃO gerencia as configurações da empresa (settings.manage) não consegue
 * atribuir o perfil "Administrador da empresa" — senão poderia criar para
 * si mesmo um acesso maior do que o que possui.
 */
trait ValidatesCompanyMembership
{
    /**
     * @param  int|null  $currentRoleId  perfil atual (edição): mantê-lo é sempre permitido
     * @return array<string, mixed>
     */
    protected function membershipRules(?int $currentRoleId = null): array
    {
        $companyId = $this->activeCompanyId();

        return [
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('company_id', $companyId),
                function (string $attribute, mixed $value, Closure $fail) use ($companyId, $currentRoleId): void {
                    if ((int) $value === $currentRoleId || $this->user()?->hasPermission('settings.manage')) {
                        return;
                    }

                    $slug = DB::table('roles')->where('company_id', $companyId)->where('id', (int) $value)->value('slug');

                    if ($slug === Role::COMPANY_ADMIN) {
                        $fail('Somente administradores da empresa podem atribuir o perfil Administrador da empresa.');
                    }
                },
            ],
            'company_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('company_units', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function membershipAttributes(): array
    {
        return [
            'role_id' => 'perfil de acesso',
            'company_unit_id' => 'unidade',
            'job_title' => 'cargo',
        ];
    }

    protected function activeCompanyId(): ?int
    {
        return app(CurrentCompany::class)->id();
    }
}
