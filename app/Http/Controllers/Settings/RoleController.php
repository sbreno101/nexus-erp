<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\SaveRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RoleRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Perfis de acesso da empresa ativa e suas permissões.
 *
 * A lista de perfis é pequena (poucas dezenas), por isso a listagem é uma
 * view simples, sem Livewire nem paginação.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('settings.roles.index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'memberships'])
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(),
            'totalPermissions' => collect(config('permissions.groups'))->flatten()->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('settings.roles.create', [
            'role' => new Role,
            'groups' => config('permissions.groups'),
            'selected' => [],
        ]);
    }

    public function store(RoleRequest $request, SaveRole $saveRole): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = $saveRole->handle(null, $request->roleData());

        return redirect()->route('roles.index')->with('success', "Perfil {$role->name} criado com sucesso.");
    }

    /**
     * Quem só tem permissão de visualizar (ou abre o perfil Administrador da
     * empresa) vê o formulário em modo somente leitura.
     */
    public function edit(Role $role): View
    {
        $this->authorize('view', $role);

        return view('settings.roles.edit', [
            'role' => $role->loadCount('memberships'),
            'groups' => config('permissions.groups'),
            'selected' => $role->permissions()->pluck('name')->all(),
            'readOnly' => auth()->user()->cannot('update', $role),
        ]);
    }

    public function update(RoleRequest $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        $this->authorize('update', $role);

        $saveRole->handle($role, $request->roleData());

        return redirect()->route('roles.index')->with('success', "Perfil {$role->name} atualizado com sucesso.");
    }

    public function destroy(Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        $this->authorize('delete', $role);

        $deleteRole->handle($role);

        return redirect()->route('roles.index')->with('success', 'Perfil excluído.');
    }
}
