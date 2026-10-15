<?php

namespace App\Notifications;

use App\Models\ServiceOrder;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa um colaborador que uma OS ou chamado foi atribuído a ele.
 */
class WorkItemAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ServiceOrder|Ticket $item, public bool $sendMail = false) {}

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
            ->subject("{$this->label()} {$this->item->number} atribuído a você")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("{$this->label()} {$this->item->number}: {$this->title()}")
            ->line('Prioridade: '.$this->item->priority->label())
            ->action('Abrir', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->label()} atribuído a você",
            'message' => "{$this->item->number} — {$this->title()}",
            'url' => $this->url(),
            'company_id' => $this->item->company_id,
        ];
    }

    private function label(): string
    {
        return $this->item instanceof ServiceOrder ? 'Ordem de serviço' : 'Chamado';
    }

    private function title(): string
    {
        return $this->item instanceof ServiceOrder ? $this->item->title : $this->item->subject;
    }

    private function url(): string
    {
        return $this->item instanceof ServiceOrder
            ? route('service-orders.show', $this->item)
            : route('tickets.show', $this->item);
    }
}
