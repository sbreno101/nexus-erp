<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    actingAsRole('company-admin');
});

it('registra a operação com empresa e valores, mascarando campos sensíveis', function () {
    $customer = Customer::factory()->create();

    $log = app(AuditLogger::class)->log('customer.reviewed', $customer, null, ['status' => 'active', 'password' => 'segredo']);

    expect($log)
        ->company_id->toBe(currentCompany()->id)
        ->auditable_id->toBe($customer->id)
        ->and($log->new_values)->toBe(['status' => 'active', 'password' => '[protegido]']);
});

it('falha na auditoria dentro de uma transação não aborta a operação principal no PostgreSQL', function () {
    Log::spy();

    DB::transaction(function (): void {
        // Evento maior que a coluna (60): o INSERT da auditoria falha.
        expect(app(AuditLogger::class)->log(str_repeat('x', 100)))->toBeNull();

        // Sem o SAVEPOINT, esta consulta falharia com "current transaction is aborted".
        Customer::factory()->create(['name' => 'Cliente após falha']);
    });

    expect(Customer::query()->where('name', 'Cliente após falha')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', str_repeat('x', 100))->exists())->toBeFalse();
    Log::shouldHaveReceived('error')->once();
});
