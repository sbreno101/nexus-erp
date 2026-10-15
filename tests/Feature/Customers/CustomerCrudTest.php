<?php

use App\Models\Contract;
use App\Models\Customer;
use App\Support\Tenancy\CurrentCompany;

beforeEach(function () {
    $this->user = actingAsRole('company-admin');
});

function customerPayload(array $overrides = []): array
{
    return [
        'type' => 'company',
        'name' => 'Clínica Bem Estar Ltda',
        'trade_name' => 'Clínica Bem Estar',
        'document' => '11.222.333/0001-81',
        'email' => 'contato@bemestar.com.br',
        'status' => 'active',
        'addresses' => [
            ['label' => 'Matriz', 'zip_code' => '01310-100', 'street' => 'Av. Paulista', 'number' => '1000', 'city' => 'São Paulo', 'state' => 'sp', 'is_primary' => '1'],
        ],
        'contacts' => [
            ['name' => 'Maria Souza', 'email' => 'maria@bemestar.com.br', 'is_primary' => '1'],
        ],
        ...$overrides,
    ];
}

it('cadastra cliente com documento limpo, endereço e contato', function () {
    $this->post('/clientes', customerPayload())->assertRedirect();

    $customer = Customer::query()->with(['addresses', 'contacts'])->sole();

    expect($customer->document)->toBe('11222333000181')
        ->and($customer->addresses)->toHaveCount(1)
        ->and($customer->addresses->first()->state)->toBe('SP')
        ->and($customer->addresses->first()->zip_code)->toBe('01310100')
        ->and($customer->contacts->first()->name)->toBe('Maria Souza');
});

it('valida CPF/CNPJ conforme o tipo de pessoa', function () {
    $this->post('/clientes', customerPayload(['document' => '11.222.333/0001-82']))->assertSessionHasErrors('document');
    $this->post('/clientes', customerPayload(['type' => 'individual', 'document' => '11.222.333/0001-81']))->assertSessionHasErrors('document');
    $this->post('/clientes', customerPayload(['type' => 'individual', 'document' => '529.982.247-25']))->assertSessionHasNoErrors();
});

it('impede CPF/CNPJ duplicado na mesma empresa, mas permite em outra', function () {
    $this->post('/clientes', customerPayload());
    $this->post('/clientes', customerPayload(['name' => 'Outro']))
        ->assertSessionHasErrors(['document' => 'Já existe um cadastro com este CPF/CNPJ nesta empresa.']);

    $other = createCompany();
    app(CurrentCompany::class)->run($other, fn () => Customer::factory()->create(['document' => '11222333000181']));

    expect(Customer::query()->withoutGlobalScopes()->where('document', '11222333000181')->count())->toBe(2);
});

it('exige campos obrigatórios', function () {
    $this->post('/clientes', [])->assertSessionHasErrors(['type', 'name', 'document', 'status']);
});

it('atualiza o cliente e substitui endereços', function () {
    $this->post('/clientes', customerPayload());
    $customer = Customer::query()->sole();

    $this->put("/clientes/{$customer->id}", customerPayload([
        'name' => 'Clínica Bem Estar S.A.',
        'addresses' => [['label' => 'Filial', 'street' => 'Rua Nova', 'city' => 'Campinas', 'state' => 'SP']],
    ]))->assertRedirect("/clientes/{$customer->id}");

    expect($customer->fresh()->name)->toBe('Clínica Bem Estar S.A.')
        ->and($customer->addresses()->pluck('label')->all())->toBe(['Filial']);
});

it('exclui (soft delete) cliente sem vínculos e bloqueia com contrato ativo', function () {
    $free = Customer::factory()->create();
    $this->delete("/clientes/{$free->id}")->assertRedirect('/clientes');
    expect($free->fresh()->trashed())->toBeTrue();

    $busy = Customer::factory()->create();
    Contract::factory()->active()->create(['customer_id' => $busy->id]);

    $this->delete("/clientes/{$busy->id}")->assertSessionHas('error');
    expect($busy->fresh()->trashed())->toBeFalse();
});

it('exporta clientes em CSV respeitando a busca', function () {
    Customer::factory()->create(['name' => 'Alfa Serviços', 'trade_name' => null]);
    Customer::factory()->create(['name' => 'Beta Comércio', 'trade_name' => null]);

    $response = $this->get('/clientes/exportar?busca=Alfa')->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Alfa Serviços')->not->toContain('Beta Comércio');
});
