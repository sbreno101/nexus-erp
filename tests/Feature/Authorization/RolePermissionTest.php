<?php

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
| Permissões granulares por perfil, sempre validadas no BACKEND
| (esconder botões na tela não basta).
*/

beforeEach(function () {
    $this->company = createCompany();
});

it('perfil financeiro consulta contas a receber, mas não exclui clientes nem gerencia usuários', function () {
    $user = actingAsRole('finance', $this->company);
    $customer = Customer::factory()->create();

    $this->get('/financeiro/receber')->assertOk();
    $this->get('/financeiro/fluxo-de-caixa')->assertOk();
    $this->delete("/clientes/{$customer->id}")->assertForbidden();
    $this->get('/configuracoes/usuarios')->assertForbidden();
    $this->get('/propostas/create')->assertForbidden();

    expect($customer->fresh())->not->toBeNull();
});

it('perfil comercial cria propostas, mas não acessa o financeiro', function () {
    actingAsRole('sales', $this->company);

    $this->get('/propostas/create')->assertOk();
    $this->get('/crm/funil')->assertOk();
    $this->get('/financeiro/receber')->assertForbidden();
    $this->get('/financeiro/contas')->assertForbidden();
    $this->get('/configuracoes/auditoria')->assertForbidden();
});

it('perfil colaborador não cria clientes', function () {
    actingAsRole('collaborator', $this->company);

    $this->get('/clientes/create')->assertForbidden();
    $this->post('/clientes', ['name' => 'X', 'type' => 'company', 'document' => '11222333000181', 'status' => 'active'])->assertForbidden();
    expect(Customer::count())->toBe(0);
});

it('alterar as permissões do perfil muda o acesso imediatamente', function () {
    $user = actingAsRole('support', $this->company);
    $this->get('/financeiro/receber')->assertForbidden();

    $role = Role::query()->where('slug', 'support')->firstOrFail();
    $role->permissions()->attach(Permission::query()->where('name', 'receivables.view')->value('id'));
    $user->flushPermissionCache();

    $this->get('/financeiro/receber')->assertOk();
});

it('administrador da empresa não consegue editar o próprio perfil de administrador pela tela de perfis', function () {
    actingAsRole('company-admin', $this->company);
    $adminRole = Role::query()->where('slug', 'company-admin')->firstOrFail();

    $this->put("/configuracoes/perfis/{$adminRole->id}", ['name' => 'Admin', 'permissions' => []])->assertForbidden();
    expect($adminRole->permissions()->count())->toBe(Permission::count());
});

it('o superadministrador acessa a gestão de empresas e os demais não', function () {
    actingAsRole('company-admin', $this->company);
    $this->get('/admin/empresas')->assertForbidden();

    $super = User::factory()->superAdmin()->create(['current_company_id' => $this->company->id]);
    $this->actingAs($super)->get('/admin/empresas')->assertOk();
});

it('o PermissionSeeder entrega permissões novas ao administrador de todas as empresas, sem mexer nos outros perfis', function () {
    $other = createCompany();
    $permissionId = Permission::query()->where('name', 'receivables.view')->value('id');
    $roles = Role::query()->withoutGlobalScopes()->whereIn('company_id', [$this->company->id, $other->id]);

    (clone $roles)->get()->each(fn (Role $role) => $role->permissions()->detach($permissionId));

    $this->seed(PermissionSeeder::class);

    $admins = (clone $roles)->where('slug', 'company-admin')->get();
    $sales = (clone $roles)->where('slug', 'sales')->get();

    expect($admins)->toHaveCount(2)
        ->and($admins->every(fn (Role $role) => $role->permissions()->count() === Permission::count()))->toBeTrue()
        ->and($sales->contains(fn (Role $role) => $role->permissions()->whereKey($permissionId)->exists()))->toBeFalse();
});
