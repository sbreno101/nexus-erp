<?php

namespace App\Http\Requests\Settings;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do cadastro/edição de perfis de acesso.
 *
 * As permissões chegam como uma lista de nomes ("customers.view", ...).
 * Usamos Rule::in com os nomes carregados UMA vez, em vez de "exists" (que
 * faria uma consulta por item marcado).
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')
                    ->where('company_id', app(CurrentCompany::class)->id())
                    ->ignore($role instanceof Role ? $role->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::query()->pluck('name')->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe um perfil com este nome nesta empresa.',
            'permissions.*.in' => 'Uma das permissões selecionadas é inválida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome do perfil',
            'description' => 'descrição',
            'permissions' => 'permissões',
        ];
    }

    /**
     * Dados no formato esperado pela Action SaveRole. Quando nenhuma caixa é
     * marcada o navegador não envia "permissions": tratamos como lista vazia.
     *
     * @return array{name: string, description: string|null, permissions: list<string>}
     */
    public function roleData(): array
    {
        return [
            'name' => $this->validated('name'),
            'description' => $this->validated('description'),
            'permissions' => array_values($this->validated('permissions') ?? []),
        ];
    }
}
