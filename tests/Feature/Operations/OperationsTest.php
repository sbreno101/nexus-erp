<?php

use App\Enums\ServiceOrderStatus;
use App\Enums\TicketStatus;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use App\Notifications\WorkItemAssignedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = actingAsRole('support');
    $this->customer = Customer::factory()->create();
});

it('abre ordem de serviço com numeração e notifica o responsável', function () {
    Notification::fake();
    $technician = createUserWithRole(currentCompany(), 'collaborator');

    $this->post('/ordens-de-servico', [
        'customer_id' => $this->customer->id,
        'title' => 'Instalação de rede',
        'description' => 'Instalar 10 pontos de rede no 2º andar.',
        'priority' => 'high',
        'assigned_to' => $technician->id,
        'due_date' => today()->addDays(3)->toDateString(),
    ])->assertRedirect();

    $order = ServiceOrder::query()->sole();

    expect($order->number)->toBe('OS-'.now()->year.'-00001')
        ->and($order->status)->toBe(ServiceOrderStatus::Open);

    Notification::assertSentTo($technician, WorkItemAssignedNotification::class);
});

it('não aceita contrato de outro cliente na ordem de serviço', function () {
    $otherContract = Contract::factory()->create();

    $this->post('/ordens-de-servico', [
        'customer_id' => $this->customer->id,
        'contract_id' => $otherContract->id,
        'title' => 'Teste',
        'description' => 'Teste',
        'priority' => 'low',
    ])->assertSessionHasErrors('contract_id');
});

it('controla o ciclo de status da OS e exige a solução para concluir', function () {
    $order = ServiceOrder::factory()->create(['customer_id' => $this->customer->id]);

    $this->patch("/ordens-de-servico/{$order->id}/status", ['status' => 'completed', 'reason' => 'Feito'])->assertSessionHas('error');
    expect($order->fresh()->status)->toBe(ServiceOrderStatus::Open);

    $this->patch("/ordens-de-servico/{$order->id}/status", ['status' => 'in_progress']);
    expect($order->fresh()->started_at)->not->toBeNull();

    $this->patch("/ordens-de-servico/{$order->id}/status", ['status' => 'completed', 'reason' => 'Rede instalada e testada.']);

    expect($order->fresh())
        ->status->toBe(ServiceOrderStatus::Completed)
        ->resolution->toBe('Rede instalada e testada.')
        ->completed_at->not->toBeNull();
});

it('registra atividades com tempo gasto', function () {
    $order = ServiceOrder::factory()->create(['customer_id' => $this->customer->id]);

    $this->post("/ordens-de-servico/{$order->id}/atividades", ['description' => 'Visita técnica realizada.', 'minutes_spent' => 90])->assertRedirect();

    expect($order->activities()->sum('minutes_spent'))->toBe(90);
});

it('abre chamado e a primeira resposta pública registra o tempo de resposta', function () {
    $this->post('/chamados', [
        'customer_id' => $this->customer->id,
        'subject' => 'Impressora não funciona',
        'description' => 'Não imprime desde ontem.',
        'priority' => 'medium',
        'channel' => 'phone',
    ])->assertRedirect();

    $ticket = Ticket::query()->sole();
    expect($ticket->number)->toBe('CHM-'.now()->year.'-00001');

    $this->post("/chamados/{$ticket->id}/mensagens", ['body' => 'Cliente VIP', 'is_internal' => '1']);
    expect($ticket->fresh()->first_response_at)->toBeNull();

    $this->post("/chamados/{$ticket->id}/mensagens", ['body' => 'Estamos verificando!', 'is_internal' => '0']);

    expect($ticket->fresh())
        ->first_response_at->not->toBeNull()
        ->status->toBe(TicketStatus::InProgress)
        ->and($ticket->messages()->count())->toBe(2);
});

it('não permite mensagens em chamado encerrado', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customer->id, 'status' => TicketStatus::Closed]);

    $this->post("/chamados/{$ticket->id}/mensagens", ['body' => 'Olá'])->assertSessionHas('error');
    expect($ticket->messages()->count())->toBe(0);
});

it('não registra atividades em OS concluída ou cancelada', function (ServiceOrderStatus $status) {
    $order = ServiceOrder::factory()->create(['customer_id' => $this->customer->id, 'status' => $status]);

    $this->post("/ordens-de-servico/{$order->id}/atividades", ['description' => 'Tentativa tardia.', 'minutes_spent' => 10])
        ->assertSessionHas('error');

    expect($order->activities()->count())->toBe(0);
})->with([ServiceOrderStatus::Completed, ServiceOrderStatus::Cancelled]);
