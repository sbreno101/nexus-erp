<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resumo diário de contas a receber vencidas, enviado à equipe financeira.
 */
class OverdueReceivablesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Company $company,
        public int $count,
        public int $totalCents,
        public bool $sendMail = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->sendMail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->company->display_name}: {$this->count} contas a receber vencidas")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Existem {$this->count} títulos vencidos, totalizando ".money($this->totalCents).'.')
            ->action('Ver contas vencidas', route('receivables.index', ['situacao' => 'overdue']));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Contas a receber vencidas',
            'message' => "{$this->count} títulos vencidos (".money($this->totalCents).')',
            'url' => route('receivables.index', ['situacao' => 'overdue']),
            'company_id' => $this->company->id,
        ];
    }
}
