<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Sino de notificações do cabeçalho. Atualiza a contagem a cada 60s
 * (wire:poll) sem recarregar a página.
 */
class Bell extends Component
{
    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.notifications.bell', [
            'unreadCount' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->latest()->limit(6)->get(),
        ]);
    }
}
