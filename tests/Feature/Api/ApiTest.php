<?php

use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->company = createCompany();
    $this->user = createUserWithRole($this->company, 'company-admin', ['email' => 'api@exemplo.com.br', 'password' => 'Senha@123']);
});

function apiToken(User $user): string
{
    return $user->createToken('testes')->plainTextToken;
}

it('emite token com credenciais válidas', function () {
    $this->postJson('/api/v1/auth/token', ['email' => 'api@exemplo.com.br', 'password' => 'Senha@123', 'device_name' => 'Postman'])
        ->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'permissions', 'companies']]);
});

it('recusa credenciais inválidas no formato padrão de validação', function () {
    $this->postJson('/api/v1/auth/token', ['email' => 'api@exemplo.com.br', 'password' => 'x', 'device_name' => 'Postman'])
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['email']]);
});

it('responde 401 em português sem token', function () {
    $this->getJson('/api/v1/customers')
        ->assertUnauthorized()
        ->assertJsonPath('message', fn ($message) => str_starts_with($message, 'Não autenticado'));
});

it('lista clientes paginados com filtros', function () {
    setCurrentCompany($this->company);
    Customer::factory()->count(3)->create();
    Customer::factory()->create(['name' => 'Buscável Ltda', 'trade_name' => null]);

    $this->withToken(apiToken($this->user))->getJson('/api/v1/customers?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 4);

    $this->withToken(apiToken($this->user))->getJson('/api/v1/customers?search=Buscável')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Buscável Ltda');
});

it('cria cliente reutilizando as validações da interface web', function () {
    $token = apiToken($this->user);

    $this->withToken($token)->postJson('/api/v1/customers', ['type' => 'company', 'name' => 'Via API', 'document' => '11222333000182', 'status' => 'active'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('document');

    $this->withToken($token)->postJson('/api/v1/customers', ['type' => 'company', 'name' => 'Via API', 'document' => '11.222.333/0001-81', 'status' => 'active'])
        ->assertCreated()
        ->assertJsonPath('data.document', '11222333000181')
        ->assertJsonPath('data.status.label', 'Ativo');
});

it('atualiza cliente sem apagar endereços omitidos', function () {
    setCurrentCompany($this->company);
    $customer = Customer::factory()->create();
    $customer->addresses()->create(['street' => 'Rua A', 'city' => 'São Paulo', 'state' => 'SP', 'is_primary' => true]);

    $this->withToken(apiToken($this->user))->putJson("/api/v1/customers/{$customer->id}", [
        'type' => 'company', 'name' => 'Nome Novo', 'document' => $customer->document, 'status' => 'active',
    ])->assertOk()->assertJsonPath('data.name', 'Nome Novo');

    expect($customer->addresses()->count())->toBe(1);
});

it('executa o fluxo comercial completo pela API', function () {
    setCurrentCompany($this->company);
    $customer = Customer::factory()->create();
    $service = CatalogItem::factory()->create(['price_cents' => 15000]);
    $token = apiToken($this->user);

    $proposal = $this->withToken($token)->postJson('/api/v1/proposals', [
        'customer_id' => $customer->id,
        'title' => 'Proposta via API',
        'discount_cents' => 1000,
        'items' => [['catalog_item_id' => $service->id, 'description' => 'Consultoria', 'quantity' => '2.5', 'unit_price_cents' => 15000]],
    ])->assertCreated()
        ->assertJsonPath('data.subtotal.cents', 37500)
        ->assertJsonPath('data.total.cents', 36500)
        ->json('data');

    $this->withToken($token)->postJson("/api/v1/proposals/{$proposal['id']}/status", ['status' => 'approved'])
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['business_rule']]);

    $this->withToken($token)->postJson("/api/v1/proposals/{$proposal['id']}/status", ['status' => 'sent'])->assertOk();
    $this->withToken($token)->postJson("/api/v1/proposals/{$proposal['id']}/status", ['status' => 'approved'])
        ->assertOk()->assertJsonPath('data.status.value', 'approved');

    $this->withToken($token)->postJson("/api/v1/proposals/{$proposal['id']}/convert", [
        'start_date' => today()->toDateString(), 'billing_frequency' => 'monthly', 'billing_day' => 10, 'activate' => true,
    ])->assertCreated()
        ->assertJsonPath('data.status.value', 'active')
        ->assertJsonPath('data.amount.cents', 36500);
});

it('registra pagamento de forma idempotente pela API', function () {
    setCurrentCompany($this->company);
    $receivable = Receivable::factory()->create(['amount_cents' => 50000]);
    $account = FinancialAccount::factory()->withBalance(0)->create();
    $token = apiToken($this->user);
    $payload = ['financial_account_id' => $account->id, 'amount_cents' => 20000, 'paid_on' => today()->toDateString(), 'method' => 'pix', 'idempotency_key' => (string) Str::uuid()];

    $this->withToken($token)->postJson("/api/v1/receivables/{$receivable->id}/payments", $payload)->assertCreated();
    // Retry da mesma requisição (ex.: timeout de rede): devolve o mesmo pagamento com 200.
    $this->withToken($token)->postJson("/api/v1/receivables/{$receivable->id}/payments", $payload)->assertOk();

    expect(Payment::count())->toBe(1)
        ->and($receivable->fresh()->paid_cents)->toBe(20000)
        ->and($account->fresh()->current_balance_cents)->toBe(20000);

    $this->withToken($token)->getJson("/api/v1/receivables/{$receivable->id}")
        ->assertOk()
        ->assertJsonPath('data.outstanding.cents', 30000)
        ->assertJsonPath('data.status.value', 'partial');
});

it('aplica as permissões do perfil na API', function () {
    $seller = createUserWithRole($this->company, 'sales');

    $this->withToken(apiToken($seller))->getJson('/api/v1/receivables')->assertForbidden()
        ->assertJsonPath('message', 'Você não tem permissão para executar esta ação.');
    $this->withToken(apiToken($seller))->getJson('/api/v1/customers')->assertOk();
});

it('retorna 404 em português para registro inexistente', function () {
    $this->withToken(apiToken($this->user))->getJson('/api/v1/customers/999999')
        ->assertNotFound()
        ->assertJsonPath('message', 'Registro não encontrado.');
});

it('revoga o token atual', function () {
    $token = apiToken($this->user);

    $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertOk();
    expect($this->user->tokens()->count())->toBe(0);
});

it('limita tentativas de emissão de token com mensagem em português', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/token', ['email' => 'api@exemplo.com.br', 'password' => 'x', 'device_name' => 'Postman']);
    }

    $this->postJson('/api/v1/auth/token', ['email' => 'api@exemplo.com.br', 'password' => 'x', 'device_name' => 'Postman'])
        ->assertTooManyRequests()
        ->assertJson(['message' => 'Muitas tentativas de login. Aguarde um minuto e tente novamente.']);
});
