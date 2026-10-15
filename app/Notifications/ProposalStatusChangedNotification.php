<?php

namespace App\Notifications;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa o responsável que sua proposta foi aprovada ou recusada.
 * ShouldQueue: o envio acontece na fila (worker), sem atrasar a requisição.
 */
class ProposalStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Proposal $proposal, public bool $sendMail = false) {}

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
            ->subject("Proposta {$this->proposal->number} {$this->statusText()}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("A proposta {$this->proposal->number} — {$this->proposal->title} foi {$this->statusText()}.")
            ->action('Ver proposta', route('proposals.show', $this->proposal));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Proposta {$this->statusText()}",
            'message' => "{$this->proposal->number} — {$this->proposal->title}",
            'url' => route('proposals.show', $this->proposal),
            'company_id' => $this->proposal->company_id,
        ];
    }

    private function statusText(): string
    {
        return mb_strtolower($this->proposal->status->label());
    }
}
