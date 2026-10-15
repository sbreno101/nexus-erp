<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsForCompany;
use App\Models\Company;
use App\Models\Receivable;
use App\Models\User;
use App\Notifications\OverdueReceivablesNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Envia à equipe financeira (usuários com permissão "receivables.view")
 * um resumo das contas a receber vencidas, se a empresa habilitou o aviso.
 */
class NotifyOverdueReceivablesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RunsForCompany;

    public function __construct(public Company $company) {}

    public function handle(): void
    {
        $settings = $this->company->settingsOrDefault();

        if (! $settings->notify_overdue_receivables) {
            return;
        }

        $this->withinCompany(function (Company $company): void {
            $overdue = Receivable::query()->overdue()
                ->selectRaw('COUNT(*) AS total_count, COALESCE(SUM(amount_cents - paid_cents), 0) AS total_cents')
                ->first();

            if ((int) $overdue?->total_count === 0) {
                return;
            }

            $recipients = User::query()->active()->memberOf($company->id)->get()
                ->filter(fn (User $user) => $user->hasPermission('receivables.view', $company->id));

            Notification::send($recipients, new OverdueReceivablesNotification(
                $company,
                (int) $overdue->total_count,
                (int) $overdue->total_cents,
                sendMail: true,
            ));
        });
    }
}
