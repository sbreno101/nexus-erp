<?php

namespace App\Actions\Roles;

use App\Exceptions\BusinessRuleException;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria ou atualiza um perfil de acesso da empresa ativa e suas permissões.
 */
class SaveRole
{
    /**
     * @param  array{name: string, description?: string|null, permissions?: list<string>}  $data
     */
    public function handle(?Role $role, array $data): Role
    {
        if ($role?->isCompanyAdmin()) {
            throw new BusinessRuleException('O perfil Administrador da empresa sempre possui todas as permissões e não pode ser alterado.');
        }

        return DB::transaction(function () use ($role, $data): Role {
            $role ??= new Role(['slug' => $this->uniqueSlug($data['name']), 'is_system' => false]);
            $role->fill(['name' => $data['name'], 'description' => $data['description'] ?? null])->save();

            $permissionIds = Permission::query()->whereIn('name', $data['permissions'] ?? [])->pluck('id');
            $role->permissions()->sync($permissionIds);

            return $role;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'perfil';
        $slug = $base;
        $suffix = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
