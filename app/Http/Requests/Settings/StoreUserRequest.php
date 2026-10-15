<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Settings\Concerns\ValidatesCompanyMembership;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro (ou vínculo) de usuário na empresa ativa.
 *
 * O e-mail NÃO é "unique": se a pessoa já tiver conta (trabalha em outra
 * empresa do grupo), a Action CreateCompanyUser apenas cria o vínculo.
 */
class StoreUserRequest extends FormRequest
{
    use ValidatesCompanyMembership;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * E-mail sempre em minúsculas: evita contas duplicadas por diferença de caixa.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:255'],
            ...$this->membershipRules(),
            'send_invitation' => ['nullable', 'boolean'],
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
     * Dados no formato esperado pela Action CreateCompanyUser.
     *
     * @return array{name: string, email: string, phone: string|null, job_title: string|null, role_id: int, company_unit_id: int|null, send_invitation: bool}
     */
    public function userData(): array
    {
        return [
            'name' => $this->validated('name'),
            'email' => $this->validated('email'),
            'phone' => $this->validated('phone'),
            'job_title' => $this->validated('job_title'),
            'role_id' => (int) $this->validated('role_id'),
            'company_unit_id' => $this->filled('company_unit_id') ? (int) $this->validated('company_unit_id') : null,
            'send_invitation' => $this->boolean('send_invitation'),
        ];
    }
}
