<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Convite enviado ao usuário cadastrado por um administrador. Contém um link
 * para DEFINIR a senha (usa o mesmo mecanismo seguro de "redefinir senha").
 * Assim nenhum administrador precisa conhecer ou enviar a senha de outra pessoa.
 */
class UserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Company $company, public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject('Seu acesso ao Nexus ERP')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Você foi cadastrado(a) na empresa {$this->company->display_name} no Nexus ERP.")
            ->line('Clique no botão abaixo para definir sua senha de acesso.')
            ->action('Definir minha senha', $url)
            ->line('Este link expira em '.config('auth.passwords.users.expire').' minutos. Depois disso, use a opção "Esqueci minha senha".');
    }
}
