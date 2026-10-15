<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST — versão 1 (/api/v1)
|--------------------------------------------------------------------------
|
| Autenticação: token pessoal do Laravel Sanctum, enviado no cabeçalho
|   Authorization: Bearer <token>
| Empresa: cabeçalho opcional "X-Company-Id" (padrão: empresa atual do usuário).
|
| A API usa os MESMOS Form Requests, Policies e Actions da interface web:
| as regras de negócio são idênticas nos dois canais.
|
| Documentação: docs/13-api.md
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/token', [V1\AuthTokenController::class, 'store'])->middleware('throttle:api-token')->name('auth.token');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::delete('auth/token', [V1\AuthTokenController::class, 'destroy'])->name('auth.logout');
        Route::get('me', [V1\AuthTokenController::class, 'me'])->name('me');
        Route::get('dashboard', V1\DashboardController::class)->name('dashboard');

        Route::apiResource('customers', V1\CustomerController::class);
        Route::apiResource('suppliers', V1\SupplierController::class);
        Route::apiResource('catalog-items', V1\CatalogItemController::class)->only(['index', 'show']);

        Route::post('proposals/{proposal}/status', [V1\ProposalController::class, 'changeStatus'])->name('proposals.status');
        Route::post('proposals/{proposal}/convert', [V1\ProposalController::class, 'convert'])->name('proposals.convert');
        Route::apiResource('proposals', V1\ProposalController::class)->except('destroy');

        Route::post('contracts/{contract}/status', [V1\ContractController::class, 'changeStatus'])->name('contracts.status');
        Route::apiResource('contracts', V1\ContractController::class)->only(['index', 'show']);

        Route::post('receivables/{receivable}/payments', [V1\ReceivableController::class, 'pay'])->name('receivables.pay');
        Route::post('receivables/{receivable}/cancel', [V1\ReceivableController::class, 'cancel'])->name('receivables.cancel');
        Route::apiResource('receivables', V1\ReceivableController::class)->only(['index', 'show', 'store']);

        Route::post('payables/{payable}/payments', [V1\PayableController::class, 'pay'])->name('payables.pay');
        Route::post('payables/{payable}/cancel', [V1\PayableController::class, 'cancel'])->name('payables.cancel');
        Route::apiResource('payables', V1\PayableController::class)->only(['index', 'show', 'store']);

        Route::post('payments/{payment}/reverse', [V1\PaymentController::class, 'reverse'])->name('payments.reverse');
        Route::apiResource('financial-accounts', V1\FinancialAccountController::class)->only(['index', 'show'])
            ->parameters(['financial-accounts' => 'account']);

        Route::apiResource('service-orders', V1\ServiceOrderController::class)->except('destroy')
            ->parameters(['service-orders' => 'serviceOrder']);

        Route::post('tickets/{ticket}/messages', [V1\TicketController::class, 'storeMessage'])->name('tickets.messages');
        Route::apiResource('tickets', V1\TicketController::class)->except('destroy');
    });
});
