<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Settings\Concerns\ValidatesCompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edição de usuário: dados cadastrais + perfil e unidade na empresa ativa.
 *
 * O e-mail não é editável aqui: ele identifica a conta em TODAS as empresas
 * em que a pessoa trabalha (cada pessoa altera o próprio e-mail no perfil).
 */
class UpdateUserRequest extends FormRequest
{
    use ValidatesCompanyMembership;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:255'],
            ...$this->membershipRules($this->currentRoleId()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->membershipAttributes();
    }

    /**
     * Dados no formato esperado pela Action UpdateCompanyUser.
     *
     * @return array{name: string, phone: string|null, job_title: string|null, role_id: int, company_unit_id: int|null}
     */
    public function userData(): array
    {
        return [
            'name' => $this->validated('name'),
            'phone' => $this->validated('phone'),
            'job_title' => $this->validated('job_title'),
            'role_id' => (int) $this->validated('role_id'),
            'company_unit_id' => $this->filled('company_unit_id') ? (int) $this->validated('company_unit_id') : null,
        ];
    }

    /**
     * Perfil atual do usuário editado na empresa ativa (manter o mesmo perfil
     * é sempre permitido, mesmo que seja o de administrador).
     */
    private function currentRoleId(): ?int
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            return null;
        }

        $roleId = $user->memberships()->where('company_id', $this->activeCompanyId())->value('role_id');

        return $roleId !== null ? (int) $roleId : null;
    }
}
