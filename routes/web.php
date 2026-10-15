<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Catalog\CatalogItemController;
use App\Http\Controllers\Commercial\ContractController;
use App\Http\Controllers\Commercial\ProposalController;
use App\Http\Controllers\CompanyLogoController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\Crm\InteractionController;
use App\Http\Controllers\Crm\LeadController;
use App\Http\Controllers\Crm\OpportunityController;
use App\Http\Controllers\Crm\TaskController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\SupplierController;
use App\Http\Controllers\Finance\FinancialAccountController;
use App\Http\Controllers\Finance\PayableController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\ReceivableController;
use App\Http\Controllers\Finance\RecurringExpenseController;
use App\Http\Controllers\Finance\TransferController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Operations\ServiceOrderController;
use App\Http\Controllers\Operations\TicketController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UnitController;
use App\Http\Controllers\Settings\UserController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas web
|--------------------------------------------------------------------------
|
| As rotas de autenticação (login, logout, recuperação de senha, verificação
| de e-mail, perfil/senha) são registradas pelo Laravel Fortify.
|
| Convenção usada no projeto:
|   - Listagens (busca/filtros/ordenação em tempo real) => componentes
|     Livewire de página inteira: Route::livewire(...).
|   - Formulários e telas de detalhe => Controllers + Form Requests.
|   - Toda rota autenticada exige e-mail verificado ("verified").
|   - A autorização fina é feita nos Controllers/Livewire via Policies.
*/

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', Livewire\Dashboard::class)->name('dashboard');

    // Perfil do usuário autenticado
    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::delete('/perfil/sessoes', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy');
    Route::post('/perfil/tokens', [ProfileController::class, 'storeToken'])->name('profile.tokens.store');
    Route::delete('/perfil/tokens/{tokenId}', [ProfileController::class, 'destroyToken'])->name('profile.tokens.destroy');

    // Empresa ativa e logotipo (arquivo privado)
    Route::post('/empresa/trocar', CompanySwitchController::class)->name('company.switch');
    Route::get('/empresas/{company}/logo', CompanyLogoController::class)->name('company.logo');

    // Notificações internas
    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notificacoes/lidas', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/notificacoes/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

    // Anexos (upload, download autorizado e remoção)
    Route::post('/anexos', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/anexos/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/anexos/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    /*
    | Clientes e fornecedores
    */
    Route::livewire('/clientes', Livewire\Customers\CustomerIndex::class)->name('customers.index');
    Route::get('/clientes/exportar', [CustomerController::class, 'export'])->middleware('throttle:exports')->name('customers.export');
    Route::resource('clientes', CustomerController::class)->except('index')
        ->parameters(['clientes' => 'customer'])->names('customers');

    Route::livewire('/fornecedores', Livewire\Customers\SupplierIndex::class)->name('suppliers.index');
    Route::get('/fornecedores/exportar', [SupplierController::class, 'export'])->middleware('throttle:exports')->name('suppliers.export');
    Route::resource('fornecedores', SupplierController::class)->except('index')
        ->parameters(['fornecedores' => 'supplier'])->names('suppliers');

    /*
    | CRM
    */
    Route::livewire('/crm/leads', Livewire\Crm\LeadIndex::class)->name('leads.index');
    Route::get('/crm/leads/{lead}/converter', [LeadController::class, 'convertForm'])->name('leads.convert');
    Route::post('/crm/leads/{lead}/converter', [LeadController::class, 'convert'])->name('leads.convert.store');
    Route::resource('crm/leads', LeadController::class)->except('index')->names('leads');

    Route::livewire('/crm/funil', Livewire\Crm\Pipeline::class)->name('opportunities.pipeline');
    Route::livewire('/crm/oportunidades', Livewire\Crm\OpportunityIndex::class)->name('opportunities.index');
    Route::resource('crm/oportunidades', OpportunityController::class)->except('index')
        ->parameters(['oportunidades' => 'opportunity'])->names('opportunities');

    Route::livewire('/crm/configuracoes', Livewire\Crm\CrmSettings::class)->name('crm.settings');

    Route::livewire('/tarefas', Livewire\Crm\TaskIndex::class)->name('tasks.index');
    Route::post('/tarefas', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tarefas/{task}/concluir', [TaskController::class, 'toggle'])->name('tasks.toggle');
    Route::delete('/tarefas/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::post('/interacoes', [InteractionController::class, 'store'])->name('interactions.store');
    Route::delete('/interacoes/{interaction}', [InteractionController::class, 'destroy'])->name('interactions.destroy');

    /*
    | Catálogo
    */
    Route::livewire('/catalogo', Livewire\Catalog\CatalogIndex::class)->name('catalog.index');
    Route::livewire('/catalogo/categorias', Livewire\Catalog\CategoryManager::class)->name('catalog.categories');
    Route::resource('catalogo', CatalogItemController::class)->except('index')
        ->parameters(['catalogo' => 'catalogItem'])->names('catalog');

    /*
    | Propostas e contratos
    */
    Route::livewire('/propostas', Livewire\Commercial\ProposalIndex::class)->name('proposals.index');
    Route::patch('/propostas/{proposal}/status', [ProposalController::class, 'changeStatus'])->name('proposals.status');
    Route::get('/propostas/{proposal}/pdf', [ProposalController::class, 'pdf'])->name('proposals.pdf');
    Route::get('/propostas/{proposal}/converter', [ProposalController::class, 'convertForm'])->name('proposals.convert');
    Route::post('/propostas/{proposal}/converter', [ProposalController::class, 'convert'])->name('proposals.convert.store');
    Route::resource('propostas', ProposalController::class)->except('index')
        ->parameters(['propostas' => 'proposal'])->names('proposals');

    Route::livewire('/contratos', Livewire\Commercial\ContractIndex::class)->name('contracts.index');
    Route::patch('/contratos/{contract}/status', [ContractController::class, 'changeStatus'])->name('contracts.status');
    Route::post('/contratos/{contract}/renovar', [ContractController::class, 'renew'])->name('contracts.renew');
    Route::post('/contratos/{contract}/gerar-cobrancas', [ContractController::class, 'generateCharges'])->name('contracts.charges');
    Route::resource('contratos', ContractController::class)->except(['index', 'destroy'])
        ->parameters(['contratos' => 'contract'])->names('contracts');

    /*
    | Operações: ordens de serviço e chamados
    */
    Route::livewire('/ordens-de-servico', Livewire\Operations\ServiceOrderIndex::class)->name('service-orders.index');
    Route::patch('/ordens-de-servico/{serviceOrder}/status', [ServiceOrderController::class, 'changeStatus'])->name('service-orders.status');
    Route::post('/ordens-de-servico/{serviceOrder}/atividades', [ServiceOrderController::class, 'storeActivity'])->name('service-orders.activities.store');
    Route::resource('ordens-de-servico', ServiceOrderController::class)->except('index')
        ->parameters(['ordens-de-servico' => 'serviceOrder'])->names('service-orders');

    Route::livewire('/chamados', Livewire\Operations\TicketIndex::class)->name('tickets.index');
    Route::livewire('/chamados/categorias', Livewire\Operations\TicketCategoryManager::class)->name('tickets.categories');
    Route::patch('/chamados/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
    Route::post('/chamados/{ticket}/mensagens', [TicketController::class, 'storeMessage'])->name('tickets.messages.store');
    Route::resource('chamados', TicketController::class)->except('index')
        ->parameters(['chamados' => 'ticket'])->names('tickets');

    /*
    | Financeiro
    */
    Route::livewire('/financeiro/receber', Livewire\Finance\ReceivableIndex::class)->name('receivables.index');
    Route::patch('/financeiro/receber/{receivable}/cancelar', [ReceivableController::class, 'cancel'])->name('receivables.cancel');
    Route::post('/financeiro/receber/{receivable}/pagamentos', [PaymentController::class, 'storeForReceivable'])->name('receivables.payments.store');
    Route::resource('financeiro/receber', ReceivableController::class)->except(['index', 'destroy'])
        ->parameters(['receber' => 'receivable'])->names('receivables');

    Route::livewire('/financeiro/pagar', Livewire\Finance\PayableIndex::class)->name('payables.index');
    Route::patch('/financeiro/pagar/{payable}/cancelar', [PayableController::class, 'cancel'])->name('payables.cancel');
    Route::post('/financeiro/pagar/{payable}/pagamentos', [PaymentController::class, 'storeForPayable'])->name('payables.payments.store');
    Route::resource('financeiro/pagar', PayableController::class)->except(['index', 'destroy'])
        ->parameters(['pagar' => 'payable'])->names('payables');

    Route::patch('/financeiro/pagamentos/{payment}/estornar', [PaymentController::class, 'reverse'])->name('payments.reverse');

    Route::resource('financeiro/despesas-recorrentes', RecurringExpenseController::class)->except('show')
        ->parameters(['despesas-recorrentes' => 'recurringExpense'])->names('recurring-expenses');

    Route::post('/financeiro/contas/{account}/lancamentos', [FinancialAccountController::class, 'storeTransaction'])->name('accounts.transactions.store');
    Route::patch('/financeiro/lancamentos/{transaction}/estornar', [FinancialAccountController::class, 'reverseTransaction'])->name('accounts.transactions.reverse');
    Route::resource('financeiro/contas', FinancialAccountController::class)->except('destroy')
        ->parameters(['contas' => 'account'])->names('accounts');

    Route::get('/financeiro/transferencias/nova', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('/financeiro/transferencias', [TransferController::class, 'store'])->name('transfers.store');
    Route::patch('/financeiro/transferencias/{transfer}/estornar', [TransferController::class, 'reverse'])->name('transfers.reverse');

    Route::livewire('/financeiro/fluxo-de-caixa', Livewire\Finance\CashFlow::class)->name('cash-flow.index');
    Route::livewire('/financeiro/configuracoes', Livewire\Finance\FinanceSettings::class)->name('finance.settings');

    /*
    | Relatórios
    */
    Route::get('/relatorios', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/relatorios/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/relatorios/{report}/exportar/{format}', [ReportController::class, 'export'])
        ->whereIn('format', ['csv', 'pdf'])->middleware('throttle:exports')->name('reports.export');

    /*
    | Administração da empresa
    */
    Route::get('/configuracoes/empresa', [CompanySettingsController::class, 'edit'])->name('settings.company.edit');
    Route::put('/configuracoes/empresa', [CompanySettingsController::class, 'update'])->name('settings.company.update');

    Route::livewire('/configuracoes/usuarios', Livewire\Settings\UserIndex::class)->name('users.index');
    Route::patch('/configuracoes/usuarios/{user}/acesso', [UserController::class, 'toggleAccess'])->name('users.toggle');
    Route::resource('configuracoes/usuarios', UserController::class)->only(['create', 'store', 'edit', 'update'])
        ->parameters(['usuarios' => 'user'])->names('users');

    Route::resource('configuracoes/perfis', RoleController::class)->except('show')
        ->parameters(['perfis' => 'role'])->names('roles');

    Route::resource('configuracoes/unidades', UnitController::class)->except('show')
        ->parameters(['unidades' => 'unit'])->names('units');

    Route::livewire('/configuracoes/auditoria', Livewire\Settings\AuditLogIndex::class)->name('audit.index');

    /*
    | Plataforma (somente superadministrador)
    */
    Route::resource('admin/empresas', CompanyController::class)->except(['show', 'destroy'])
        ->parameters(['empresas' => 'company'])->names('admin.companies');
});
