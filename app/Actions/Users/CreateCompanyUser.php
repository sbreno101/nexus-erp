<?php

namespace App\Actions\Users;

use App\Exceptions\BusinessRuleException;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Adiciona um usuário à empresa ativa.
 *
 * - Se o e-mail ainda não existe, cria a conta com uma senha aleatória e
 *   envia um CONVITE com link para o próprio usuário definir a senha.
 * - Se o e-mail já existe (pessoa que trabalha em outra empresa do grupo),
 *   apenas cria o vínculo com a empresa ativa.
 */
class CreateCompanyUser
{
    public function __construct(private CurrentCompany $currentCompany) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, job_title?: string|null, role_id: int, company_unit_id?: int|null, send_invitation?: bool}  $data
     */
    public function handle(array $data): User
    {
        $company = $this->currentCompany->getOrFail();

        [$user, $isNewUser] = DB::transaction(function () use ($data, $company): array {
            $user = User::query()->where('email', mb_strtolower($data['email']))->first();
            $isNewUser = $user === null;

            if ($isNewUser) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => mb_strtolower($data['email']),
                    'phone' => $data['phone'] ?? null,
                    'job_title' => $data['job_title'] ?? null,
                    'password' => Str::password(32),
                    'current_company_id' => $company->id,
                ]);
            } elseif (CompanyMembership::query()->where('company_id', $company->id)->where('user_id', $user->id)->exists()) {
                throw new BusinessRuleException('Este usuário já está vinculado à empresa.');
            }

            CompanyMembership::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role_id' => $data['role_id'],
                'company_unit_id' => $data['company_unit_id'] ?? null,
                'is_active' => true,
            ]);

            return [$user, $isNewUser];
        });

        if ($isNewUser && ($data['send_invitation'] ?? true)) {
            $user->notify(new UserInvitationNotification($company, Password::broker()->createToken($user)));
        }

        return $user;
    }
}
