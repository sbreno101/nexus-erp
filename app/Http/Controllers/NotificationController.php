<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notificações internas (canal "database" do Laravel Notifications).
 * Cada usuário só acessa as próprias notificações.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    /**
     * Marca como lida e redireciona para o registro relacionado.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('notifications.index');

        // Só redireciona para URLs da própria aplicação (evita "open redirect").
        return str_starts_with($url, url('/')) ? redirect()->to($url) : redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Notificações marcadas como lidas.');
    }
}
