<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Dashboard\DashboardMetrics;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Indicadores do dashboard da empresa ativa. ?from=AAAA-MM-DD&to=AAAA-MM-DD
 * Valores monetários em centavos (sufixo _cents).
 */
class DashboardController extends ApiController
{
    public function __invoke(Request $request, CurrentCompany $currentCompany): JsonResponse
    {
        $this->authorize('dashboard.view');

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? now()->subMonths(5)->startOfMonth());
        $to = CarbonImmutable::parse($validated['to'] ?? now()->endOfMonth());
        $metrics = new DashboardMetrics([$currentCompany->id()], $from->startOfDay(), $to->endOfDay());

        return response()->json([
            'data' => [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'kpis' => $metrics->kpis(),
                'revenue_vs_expenses' => $metrics->monthlyRevenueVsExpenses(),
                'cash_flow' => $metrics->cashFlow(),
            ],
        ]);
    }
}
