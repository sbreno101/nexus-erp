<?php

use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\Receivable;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;

/*
| Isolamento multiempresa: dados de uma empresa nunca podem ser vistos ou
| alterados por usuários de outra empresa — nem pela tela, nem pela API,
| nem manipulando IDs na URL.
*/

beforeEach(function () {
    $this->companyA = createCompany(['trade_name' => 'Empresa A']);
    $this->companyB = createCompany(['trade_name' => 'Empresa B']);

    $this->userA = createUserWithRole($this->companyA, 'company-admin');

    app(CurrentCompany::class)->run($this->companyB, function (): void {
        $this->customerB = Customer::factory()->create(['name' => 'Cliente Secreto da B']);
        $this->receivableB = Receivable::factory()->create(['customer_id' => $this->customerB->id]);
    });

    app(CurrentCompany::class)->run($this->companyA, function (): void {
        $this->customerA = Customer::factory()->create(['name' => 'Cliente da A']);
    });
});

it('a listagem mostra apenas os clientes da empresa ativa', function () {
    $this->actingAs($this->userA)->get('/clientes')
        ->assertOk()
        ->assertSee('Cliente da A')
        ->assertDontSee('Cliente Secreto da B');
});

it('responde 404 ao acessar registro de outra empresa pela URL', function (string $method, string $suffix) {
    $this->actingAs($this->userA)
        ->call($method, "/clientes/{$this->customerB->id}{$suffix}", ['name' => 'Hackeado', 'type' => 'company', 'document' => '11222333000181', 'status' => 'active'])
        ->assertNotFound();

    expect($this->customerB->fresh()->name)->toBe('Cliente Secreto da B');
})->with([
    ['GET', ''],
    ['GET', '/edit'],
    ['PUT', ''],
    ['DELETE', ''],
]);

it('não permite registrar pagamento em título de outra empresa', function () {
    $this->actingAs($this->userA)
        ->post("/financeiro/receber/{$this->receivableB->id}/pagamentos", [
            'financial_account_id' => 1, 'amount' => '10,00', 'paid_on' => today()->toDateString(), 'method' => 'pix', 'idempotency_key' => fake()->uuid(),
        ])
        ->assertNotFound();

    expect($this->receivableB->fresh()->paid_cents)->toBe(0);
});

it('não aceita relacionar registros de outra empresa em formulários', function () {
    $this->actingAs($this->userA)
        ->post('/financeiro/receber', [
            'customer_id' => $this->customerB->id,
            'description' => 'Tentativa',
            'amount' => '100,00',
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays(5)->toDateString(),
        ])
        ->assertSessionHasErrors('customer_id');
});

it('não permite trocar para uma empresa sem vínculo', function () {
    $this->actingAs($this->userA)
        ->post('/empresa/trocar', ['company_id' => $this->companyB->id])
        ->assertForbidden();

    expect($this->userA->fresh()->current_company_id)->toBe($this->companyA->id);
});

it('usuário vinculado a duas empresas enxerga apenas a empresa ativa', function () {
    $shared = createUserWithRole($this->companyA, 'company-admin');
    CompanyMembership::create([
        'company_id' => $this->companyB->id,
        'user_id' => $shared->id,
        'role_id' => Role::query()->withoutGlobalScopes()->where('company_id', $this->companyB->id)->where('slug', 'sales')->value('id'),
        'is_active' => true,
    ]);

    $this->actingAs($shared)->get('/clientes')->assertSee('Cliente da A')->assertDontSee('Cliente Secreto da B');

    $this->post('/empresa/trocar', ['company_id' => $this->companyB->id])->assertRedirect('/dashboard');

    $this->get('/clientes')->assertSee('Cliente Secreto da B')->assertDontSee('Cliente da A');
});

it('o escopo global filtra consultas e preenche company_id automaticamente', function () {
    setCurrentCompany($this->companyA);

    expect(Customer::query()->pluck('name')->all())->toBe(['Cliente da A']);

    $created = Customer::factory()->create();
    expect($created->company_id)->toBe($this->companyA->id);

    expect(fn () => $created->update(['company_id' => $this->companyB->id]))->toThrow(RuntimeException::class);
});

it('a API recusa X-Company-Id de empresa sem acesso', function () {
    $token = $this->userA->createToken('teste')->plainTextToken;

    $this->withToken($token)->withHeader('X-Company-Id', (string) $this->companyB->id)
        ->getJson('/api/v1/customers')
        ->assertForbidden();

    $this->flushHeaders();
    $this->withToken($token)->getJson("/api/v1/customers/{$this->customerB->id}")->assertNotFound();
});

it('membro com vínculo desativado perde o acesso à empresa', function () {
    $member = createUserWithRole($this->companyA, 'sales');
    $member->memberships()->update(['is_active' => false]);

    $this->actingAs($member)->get('/dashboard')->assertRedirect('/login');
    expect(User::find($member->id)->accessibleCompanies())->toBeEmpty();
});
