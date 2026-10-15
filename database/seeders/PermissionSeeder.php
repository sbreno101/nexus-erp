<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Seeder;

/**
 * Sincroniza a tabela "permissions" com config/permissions.php e garante
 * que o perfil "Administrador da empresa" (permissões "*") de TODAS as
 * empresas receba as permissões novas. Os demais perfis não são alterados,
 * pois podem ter sido personalizados pelos administradores.
 *
 * Pode ser executado a qualquer momento (é idempotente):
 *   php artisan db:seed --class=PermissionSeeder
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [];

        foreach (config('permissions.groups') as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                Permission::query()->updateOrCreate(['name' => $name], ['group' => $group, 'label' => $label]);
                $names[] = $name;
            }
        }

        // Permissões removidas do arquivo de configuração deixam de existir.
        Permission::query()->whereNotIn('name', $names)->delete();

        $allPermissionIds = Permission::query()->pluck('id')->all();

        Role::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('slug', 'company-admin')
            ->each(fn (Role $role) => $role->permissions()->sync($allPermissionIds));
    }
}
