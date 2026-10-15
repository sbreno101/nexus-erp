<?php

use App\Models\CompanyMembership;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = actingAsRole('company-admin');
    $this->salesRole = Role::query()->where('slug', 'sales')->firstOrFail();
});

it('cadastra usuário novo e envia convite para definir a senha', function () {
    Notification::fake();

    $this->post('/configuracoes/usuarios', [
        'name' => 'Nova Vendedora',
        'email' => 'NOVA@exemplo.com.br',
        'role_id' => $this->salesRole->id,
        'send_invitation' => '1',
    ])->assertRedirect();

    $user = User::query()->where('email', 'nova@exemplo.com.br')->sole();

    expect($user->membershipFor(currentCompany())->role_id)->toBe($this->salesRole->id);
    Notification::assertSentTo($user, UserInvitationNotification::class);
});

it('vincula usuário existente de outra empresa sem criar nova conta', function () {
    $other = createCompany();
    $existing = createUserWithRole($other, 'finance', ['email' => 'compartilhado@exemplo.com.br']);

    $this->post('/configuracoes/usuarios', [
        'name' => 'Compartilhado',
        'email' => 'compartilhado@exemplo.com.br',
        'role_id' => $this->salesRole->id,
    ])->assertRedirect();

    expect(User::query()->where('email', 'compartilhado@exemplo.com.br')->count())->toBe(1)
        ->and($existing->memberships()->count())->toBe(2);
});

it('desativa e reativa o acesso do usuário à empresa', function () {
    $member = createUserWithRole(currentCompany(), 'sales');

    $this->patch("/configuracoes/usuarios/{$member->id}/acesso", ['active' => '0'])->assertRedirect();
    expect(CompanyMembership::query()->where('user_id', $member->id)->value('is_active'))->toBeFalse();

    $this->patch("/configuracoes/usuarios/{$member->id}/acesso", ['active' => '1'])->assertRedirect();
    expect(CompanyMembership::query()->where('user_id', $member->id)->value('is_active'))->toBeTrue();
});

it('não permite desativar a si mesmo', function () {
    $this->patch("/configuracoes/usuarios/{$this->admin->id}/acesso", ['active' => '0'])->assertForbidden();
});

it('cria perfil personalizado com permissões selecionadas', function () {
    $this->post('/configuracoes/perfis', [
        'name' => 'Estagiário',
        'description' => 'Somente consulta',
        'permissions' => ['dashboard.view', 'customers.view'],
    ])->assertRedirect();

    $role = Role::query()->where('name', 'Estagiário')->sole();

    expect($role->slug)->toBe('estagiario')
        ->and($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['customers.view', 'dashboard.view']);
});

it('desativar o vínculo com uma empresa preserva sessões e tokens usados em outras empresas', function () {
    $member = createUserWithRole(createCompany(), 'finance');
    $salesRole = $this->salesRole;
    CompanyMembership::create(['company_id' => currentCompany()->id, 'user_id' => $member->id, 'role_id' => $salesRole->id, 'is_active' => true]);
    $member->createToken('integração');

    $this->patch("/configuracoes/usuarios/{$member->id}/acesso", ['active' => '0'])->assertRedirect();

    expect($member->tokens()->count())->toBe(1)
        ->and($member->fresh()->accessibleCompanies()->pluck('id')->all())->not->toContain(currentCompany()->id);
});

it('desativar o último vínculo ativo revoga os tokens do usuário', function () {
    $member = createUserWithRole(currentCompany(), 'sales');
    $member->createToken('integração');

    $this->patch("/configuracoes/usuarios/{$member->id}/acesso", ['active' => '0'])->assertRedirect();

    expect($member->tokens()->count())->toBe(0);
});
