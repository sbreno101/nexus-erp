<?php

use App\Models\CatalogItem;
use App\Models\CompanyUnit;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Payable;
use App\Models\Proposal;
use App\Models\Receivable;
use App\Models\RecurringExpense;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\DemoDataSeeder;

/*
| Teste de fumaça: com os dados demonstrativos completos, todas as telas
| principais precisam abrir (HTTP 200) para o administrador da empresa —
| e um perfil restrito precisa ser bloqueado (403) nas áreas que não lhe cabem.
| Pega erros de Blade, relacionamentos não carregados (N+1 bloqueado) e rotas quebradas.
*/

beforeEach(function () {
    AuditLogger::withoutAuditing(fn () => $this->seed(DemoDataSeeder::class));
    auth()->forgetUser();
    app(CurrentCompany::class)->set(null);
});

/**
 * Executa um callback "dentro" da empresa demo principal para obter ids.
 */
function inMainCompany(callable $callback): mixed
{
    $admin = User::query()->where('email', 'admin@verticeti.local')->firstOrFail();

    return app(CurrentCompany::class)->run($admin->currentCompany, $callback);
}

it('abre todas as telas principais para o administrador da empresa', function () {
    $ids = inMainCompany(fn () => [
        'customer' => Customer::query()->value('id'),
        'supplier' => Supplier::query()->value('id'),
        'lead' => Lead::query()->value('id'),
        'opportunity' => Opportunity::query()->value('id'),
        'item' => CatalogItem::query()->value('id'),
        'proposal' => Proposal::query()->value('id'),
        'contract' => Contract::query()->value('id'),
        'order' => ServiceOrder::query()->value('id'),
        'ticket' => Ticket::query()->value('id'),
        'receivable' => Receivable::query()->value('id'),
        'payable' => Payable::query()->value('id'),
        'account' => FinancialAccount::query()->value('id'),
        'recurring' => RecurringExpense::query()->value('id'),
        'unit' => CompanyUnit::query()->value('id'),
        'role' => Role::query()->where('slug', 'sales')->value('id'),
        'user' => User::query()->where('email', 'comercial@verticeti.local')->value('id'),
    ]);

    $this->actingAs(User::query()->where('email', 'admin@verticeti.local')->firstOrFail());

    $urls = [
        '/dashboard', '/dashboard?empresa=all', '/perfil', '/notificacoes',
        '/clientes', '/clientes/create', "/clientes/{$ids['customer']}", "/clientes/{$ids['customer']}/edit", '/clientes/exportar',
        '/fornecedores', '/fornecedores/create', "/fornecedores/{$ids['supplier']}", "/fornecedores/{$ids['supplier']}/edit",
        '/crm/leads', '/crm/leads/create', "/crm/leads/{$ids['lead']}", "/crm/leads/{$ids['lead']}/edit",
        '/crm/funil', '/crm/oportunidades', '/crm/oportunidades/create', "/crm/oportunidades/{$ids['opportunity']}", "/crm/oportunidades/{$ids['opportunity']}/edit",
        '/crm/configuracoes', '/tarefas',
        '/catalogo', '/catalogo/categorias', '/catalogo/create', "/catalogo/{$ids['item']}", "/catalogo/{$ids['item']}/edit",
        '/propostas', '/propostas/create', "/propostas/{$ids['proposal']}", "/propostas/{$ids['proposal']}/pdf",
        '/contratos', '/contratos/create', "/contratos/{$ids['contract']}", "/contratos/{$ids['contract']}/edit",
        '/ordens-de-servico', '/ordens-de-servico/create', "/ordens-de-servico/{$ids['order']}", "/ordens-de-servico/{$ids['order']}/edit",
        '/chamados', '/chamados/categorias', '/chamados/create', "/chamados/{$ids['ticket']}", "/chamados/{$ids['ticket']}/edit",
        '/financeiro/receber', '/financeiro/receber?situacao=overdue', '/financeiro/receber/create', "/financeiro/receber/{$ids['receivable']}",
        '/financeiro/pagar', '/financeiro/pagar/create', "/financeiro/pagar/{$ids['payable']}",
        '/financeiro/despesas-recorrentes', '/financeiro/despesas-recorrentes/create', "/financeiro/despesas-recorrentes/{$ids['recurring']}/edit",
        '/financeiro/contas', '/financeiro/contas/create', "/financeiro/contas/{$ids['account']}", "/financeiro/contas/{$ids['account']}/edit",
        '/financeiro/transferencias/nova', '/financeiro/fluxo-de-caixa', '/financeiro/configuracoes',
        '/relatorios', '/relatorios/clientes', '/relatorios/comercial', '/relatorios/contratos', '/relatorios/contas-a-receber',
        '/relatorios/contas-a-pagar', '/relatorios/fluxo-de-caixa', '/relatorios/inadimplencia', '/relatorios/ordens-de-servico',
        '/relatorios/produtividade-atendimento', '/relatorios/inadimplencia/exportar/csv', '/relatorios/contratos/exportar/pdf',
        '/configuracoes/empresa', '/configuracoes/usuarios', '/configuracoes/usuarios/create', "/configuracoes/usuarios/{$ids['user']}/edit",
        '/configuracoes/perfis', '/configuracoes/perfis/create', "/configuracoes/perfis/{$ids['role']}/edit",
        '/configuracoes/unidades', '/configuracoes/unidades/create', "/configuracoes/unidades/{$ids['unit']}/edit",
        '/configuracoes/auditoria',
    ];

    foreach ($urls as $url) {
        $response = $this->get($url);

        $status = $response->baseResponse->getStatusCode();

        expect($status)->toBe(200, "Falha ao abrir {$url}: HTTP {$status}");
    }
});

it('bloqueia áreas administrativas e financeiras para o perfil colaborador', function () {
    $this->actingAs(User::query()->where('email', 'colaborador@verticeti.local')->firstOrFail());

    foreach (['/financeiro/receber', '/financeiro/contas', '/configuracoes/usuarios', '/configuracoes/auditoria', '/propostas', '/relatorios', '/admin/empresas', '/financeiro/fluxo-de-caixa'] as $url) {
        expect($this->get($url)->status())->toBe(403, "Deveria bloquear {$url}");
    }

    foreach (['/dashboard', '/ordens-de-servico', '/chamados', '/clientes'] as $url) {
        expect($this->get($url)->status())->toBe(200, "Deveria permitir {$url}");
    }
});

it('permite ao superadministrador gerenciar empresas', function () {
    $this->actingAs(User::query()->where('email', 'admin@nexuserp.local')->firstOrFail());

    $this->get('/admin/empresas')->assertOk();
    $this->get('/admin/empresas/create')->assertOk();
});
