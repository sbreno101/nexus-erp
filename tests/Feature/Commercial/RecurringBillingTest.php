<?php

use App\Actions\Contracts\ChangeContractStatus;
use App\Actions\Contracts\GenerateContractCharges;
use App\Actions\Contracts\ProcessContractLifecycle;
use App\Actions\Contracts\RenewContract;
use App\Actions\Finance\GenerateRecurringExpenses;
use App\Enums\BillStatus;
use App\Enums\ContractStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Contract;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\RecurringExpense;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    actingAsRole('manager');
    $this->today = CarbonImmutable::parse('2026-10-14');
    $this->travelTo($this->today);
});

it('gera a cobrança do contrato ativo dentro da janela de antecedência', function () {
    $contract = Contract::factory()->active('2026-10-20')->create(['amount_cents' => 150_000, 'billing_day' => 20]);

    $created = app(GenerateContractCharges::class)->forCurrentCompany($this->today);

    $receivable = Receivable::where('contract_id', $contract->id)->sole();

    expect($created)->toBe(1)
        ->and($receivable->amount_cents)->toBe(150_000)
        ->and($receivable->due_date->toDateString())->toBe('2026-10-20')
        ->and($receivable->competence->toDateString())->toBe('2026-10-01')
        ->and($contract->fresh()->next_billing_date->toDateString())->toBe('2026-11-20');
});

it('é idempotente: executar duas vezes não duplica cobranças', function () {
    Contract::factory()->active('2026-10-20')->create(['billing_day' => 20]);
    $action = app(GenerateContractCharges::class);

    $action->forCurrentCompany($this->today);
    $action->forCurrentCompany($this->today);

    expect(Receivable::count())->toBe(1);
});

it('o banco impede duas cobranças do mesmo contrato na mesma competência', function () {
    $contract = Contract::factory()->active()->create();
    $attributes = [
        'customer_id' => $contract->customer_id,
        'contract_id' => $contract->id,
        'description' => 'Cobrança',
        'amount_cents' => 1000,
        'issue_date' => '2026-10-01',
        'due_date' => '2026-10-10',
        'competence' => '2026-10-01',
    ];

    Receivable::create($attributes);
    Receivable::create($attributes);
})->throws(UniqueConstraintViolationException::class);

it('não gera cobranças para contratos suspensos, em rascunho ou fora da janela', function () {
    Contract::factory()->active('2026-10-20')->create(['status' => ContractStatus::Suspended]);
    Contract::factory()->create(['next_billing_date' => '2026-10-20']);
    Contract::factory()->active('2026-12-20')->create();

    expect(app(GenerateContractCharges::class)->forCurrentCompany($this->today))->toBe(0);
});

it('respeita a periodicidade trimestral', function () {
    $contract = Contract::factory()->active('2026-10-10')->create(['billing_frequency' => 'quarterly', 'billing_day' => 10]);

    app(GenerateContractCharges::class)->forCurrentCompany($this->today);

    expect($contract->fresh()->next_billing_date->toDateString())->toBe('2027-01-10');
});

it('não gera cobrança com vencimento após o término do contrato', function () {
    $contract = Contract::factory()->active('2026-10-20')->create(['end_date' => '2026-10-15']);

    expect(app(GenerateContractCharges::class)->forCurrentCompany($this->today))->toBe(0)
        ->and($contract->fresh()->next_billing_date)->toBeNull();
});

it('ativa contrato definindo a primeira data de cobrança', function () {
    $contract = Contract::factory()->create(['start_date' => '2026-10-14', 'billing_day' => 10]);

    app(ChangeContractStatus::class)->handle($contract, ContractStatus::Active);

    expect($contract->fresh())
        ->status->toBe(ContractStatus::Active)
        ->and($contract->fresh()->next_billing_date->toDateString())->toBe('2026-11-10');
});

it('impede transições inválidas (ex.: reativar contrato cancelado)', function () {
    $contract = Contract::factory()->active()->create();
    app(ChangeContractStatus::class)->handle($contract, ContractStatus::Cancelled, 'Cliente encerrou as atividades');

    app(ChangeContractStatus::class)->handle($contract->fresh(), ContractStatus::Active);
})->throws(BusinessRuleException::class, 'Transição inválida');

it('cancelar contrato cancela cobranças futuras em aberto e preserva as pagas/vencidas', function () {
    $contract = Contract::factory()->active()->create();
    $future = Receivable::factory()->dueIn(25)->create(['contract_id' => $contract->id, 'customer_id' => $contract->customer_id]);
    $overdue = Receivable::factory()->overdue()->create(['contract_id' => $contract->id, 'customer_id' => $contract->customer_id]);

    app(ChangeContractStatus::class)->handle($contract, ContractStatus::Cancelled, 'Rescisão amigável');

    expect($future->fresh()->status)->toBe(BillStatus::Cancelled)
        ->and($overdue->fresh()->status)->toBe(BillStatus::Open)
        ->and($contract->fresh()->next_billing_date)->toBeNull();
});

it('reativação após suspensão não cobra o período suspenso', function () {
    $contract = Contract::factory()->active('2026-08-10')->create(['billing_day' => 10, 'status' => ContractStatus::Suspended]);

    app(ChangeContractStatus::class)->handle($contract, ContractStatus::Active);

    expect($contract->fresh()->next_billing_date->toDateString())->toBe('2026-11-10');
});

it('renova a vigência e o processo diário encerra ou renova contratos vencidos', function () {
    $renewable = Contract::factory()->active()->create(['end_date' => '2026-10-31', 'renewal_months' => 12]);
    app(RenewContract::class)->handle($renewable);
    expect($renewable->fresh()->end_date->toDateString())->toBe('2027-10-31');

    $toFinish = Contract::factory()->active()->create(['end_date' => '2026-10-01', 'auto_renew' => false]);
    $autoRenew = Contract::factory()->active()->create(['end_date' => '2026-10-01', 'auto_renew' => true, 'renewal_months' => 6]);

    $result = app(ProcessContractLifecycle::class)->forCurrentCompany($this->today);

    expect($result)->toBe(['renewed' => 1, 'finished' => 1])
        ->and($toFinish->fresh()->status)->toBe(ContractStatus::Finished)
        ->and($autoRenew->fresh()->end_date->toDateString())->toBe('2027-04-01');
});

it('gera contas a pagar das despesas recorrentes sem duplicar', function () {
    $expense = RecurringExpense::factory()->create(['next_due_date' => '2026-10-20', 'due_day' => 20, 'amount_cents' => 80_000]);
    $action = app(GenerateRecurringExpenses::class);

    expect($action->forCurrentCompany(10, $this->today))->toBe(1)
        ->and($action->forCurrentCompany(10, $this->today))->toBe(0);

    $payable = Payable::where('recurring_expense_id', $expense->id)->sole();

    expect($payable->amount_cents)->toBe(80_000)
        ->and($payable->due_date->toDateString())->toBe('2026-10-20')
        ->and($expense->fresh()->next_due_date->toDateString())->toBe('2026-11-20');
});

it('renovar contrato cuja cobrança parou no fim da vigência retoma as cobranças sem pular competências', function () {
    $contract = Contract::factory()->active('2026-10-20')->create(['billing_day' => 20, 'end_date' => '2026-11-10', 'renewal_months' => 12]);
    $generate = app(GenerateContractCharges::class);

    $generate->forCurrentCompany($this->today);
    $this->travelTo('2026-11-15');
    $generate->forCurrentCompany(CarbonImmutable::parse('2026-11-15'));

    expect($contract->fresh()->next_billing_date)->toBeNull();

    app(RenewContract::class)->handle($contract->fresh());
    $generate->forCurrentCompany(CarbonImmutable::parse('2026-11-15'));

    expect($contract->fresh()->end_date->toDateString())->toBe('2027-11-10')
        ->and(Receivable::where('contract_id', $contract->id)->orderBy('due_date')->pluck('due_date')->map->toDateString()->all())
        ->toBe(['2026-10-20', '2026-11-20'])
        ->and($contract->fresh()->next_billing_date->toDateString())->toBe('2026-12-20');
});

it('cobrança manual na mesma competência de outra cobrança do contrato recebe mensagem de validação amigável', function () {
    $contract = Contract::factory()->active('2026-10-20')->create(['billing_day' => 20]);
    app(GenerateContractCharges::class)->forCurrentCompany($this->today);

    $this->post('/financeiro/receber', [
        'customer_id' => $contract->customer_id,
        'contract_id' => $contract->id,
        'description' => 'Cobrança avulsa',
        'amount' => '500,00',
        'issue_date' => '2026-10-14',
        'due_date' => '2026-10-25',
        'competence' => '2026-10',
    ])->assertSessionHasErrors(['contract_id' => 'Este contrato já possui uma cobrança nesta competência.']);

    expect(Receivable::where('contract_id', $contract->id)->count())->toBe(1);
});
