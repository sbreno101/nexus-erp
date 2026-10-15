<?php

use App\Livewire\Crm\Pipeline;
use App\Livewire\Customers\CustomerIndex;
use App\Livewire\Dashboard;
use App\Livewire\Finance\FinanceSettings;
use App\Livewire\Finance\ReceivableIndex;
use App\Models\Customer;
use App\Models\FinancialCategory;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\Receivable;
use Livewire\Livewire;

/*
| Componentes Livewire testados diretamente: Livewire::test() monta o
| componente, altera propriedades/chama ações e verifica o HTML resultante.
*/

it('filtra clientes pela busca em tempo real', function () {
    actingAsRole('sales');
    Customer::factory()->create(['name' => 'Padaria Pão Quente', 'trade_name' => null]);
    Customer::factory()->create(['name' => 'Oficina Motor Bom', 'trade_name' => null]);

    Livewire::test(CustomerIndex::class)
        ->assertSee('Padaria Pão Quente')
        ->set('search', 'Oficina')
        ->assertSee('Oficina Motor Bom')
        ->assertDontSee('Padaria Pão Quente');
});

it('move oportunidade no Kanban e persiste a etapa', function () {
    actingAsRole('sales');
    $opportunity = Opportunity::factory()->create();
    $target = PipelineStage::query()->where('name', 'Negociação')->firstOrFail();

    Livewire::test(Pipeline::class)->call('move', $opportunity->id, 0, $target->id)->assertHasNoErrors();

    expect($opportunity->fresh()->pipeline_stage_id)->toBe($target->id);
});

it('perfil sem permissão não move oportunidades', function () {
    actingAsRole('collaborator');

    Livewire::test(Pipeline::class)->assertForbidden();
});

it('lista apenas títulos vencidos com o filtro de situação', function () {
    actingAsRole('finance');
    Receivable::factory()->overdue()->create(['description' => 'Mensalidade atrasada']);
    Receivable::factory()->dueIn(10)->create(['description' => 'Mensalidade futura']);

    Livewire::test(ReceivableIndex::class)
        ->set('situation', 'overdue')
        ->assertSee('Mensalidade atrasada')
        ->assertDontSee('Mensalidade futura');
});

it('cria categoria financeira pelas configurações', function () {
    actingAsRole('finance');

    Livewire::test(FinanceSettings::class)
        ->set('categoryType', 'expense')
        ->set('categoryName', 'Viagens')
        ->call('addCategory')
        ->assertHasNoErrors();

    expect(FinancialCategory::query()->where('name', 'Viagens')->exists())->toBeTrue();
});

it('dashboard consolidado não inclui empresas sem acesso', function () {
    actingAsRole('company-admin');

    Livewire::test(Dashboard::class)
        ->set('scope', '999999')
        ->assertOk()
        ->assertSee('Receita recebida');
});
