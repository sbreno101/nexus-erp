<?php

namespace App\Actions\Finance;

use App\Models\Payable;
use App\Models\RecurringExpense;
use App\Support\Billing\BillingSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Gera as contas a pagar das despesas recorrentes ativas, com a mesma
 * antecedência configurada para cobranças (billing_days_in_advance).
 *
 * Idempotente: índice UNIQUE (recurring_expense_id, competence).
 */
class GenerateRecurringExpenses
{
    public function __construct(private BillingSchedule $schedule) {}

    public function forCurrentCompany(int $daysInAdvance, ?CarbonImmutable $today = null): int
    {
        $horizon = ($today ?? CarbonImmutable::today())->addDays($daysInAdvance);
        $created = 0;

        RecurringExpense::query()
            ->where('is_active', true)
            ->whereDate('next_due_date', '<=', $horizon)
            ->eachById(function (RecurringExpense $expense) use ($horizon, &$created): void {
                $created += DB::transaction(function () use ($expense, $horizon): int {
                    /** @var RecurringExpense $locked */
                    $locked = RecurringExpense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
                    $dueDate = CarbonImmutable::parse($locked->next_due_date);
                    $count = 0;

                    while ($dueDate->lte($horizon)) {
                        if ($locked->end_date !== null && $dueDate->gt(CarbonImmutable::parse($locked->end_date))) {
                            $locked->is_active = false;
                            break;
                        }

                        $count += $this->createPayable($locked, $dueDate) ? 1 : 0;
                        $dueDate = $this->schedule->advance($dueDate, $locked->frequency, $locked->due_day);
                        $locked->next_due_date = $dueDate;
                    }

                    $locked->saveQuietly();

                    return $count;
                });
            });

        return $created;
    }

    private function createPayable(RecurringExpense $expense, CarbonImmutable $dueDate): bool
    {
        $competence = $this->schedule->competenceFor($dueDate);

        if (Payable::query()->where('recurring_expense_id', $expense->id)->whereDate('competence', $competence)->exists()) {
            return false;
        }

        try {
            DB::transaction(fn () => Payable::create([
                'supplier_id' => $expense->supplier_id,
                'recurring_expense_id' => $expense->id,
                'financial_category_id' => $expense->financial_category_id,
                'cost_center_id' => $expense->cost_center_id,
                'description' => sprintf('%s (competência %s)', $expense->description, $competence->format('m/Y')),
                'amount_cents' => $expense->amount_cents,
                'issue_date' => CarbonImmutable::today()->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'competence' => $competence->toDateString(),
                'created_by' => $expense->created_by,
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
