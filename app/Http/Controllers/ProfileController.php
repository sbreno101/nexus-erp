<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Perfil do usuário: dados pessoais e senha (rotas do Fortify), sessões
 * ativas e tokens de acesso à API (Sanctum).
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        // Sessões ativas: possível porque SESSION_DRIVER=database grava user_id, IP e navegador.
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => (object) [
                'ip_address' => $session->ip_address,
                'agent' => $this->describeAgent((string) $session->user_agent),
                'is_current' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->timezone(config('app.timezone')),
            ]);

        return view('profile.show', [
            'user' => $user,
            'sessions' => $sessions,
            'tokens' => $user->tokens()->latest()->get(),
            'memberships' => $user->memberships()->with(['company', 'role'])->get(),
        ]);
    }

    /**
     * Encerra as sessões do usuário em outros dispositivos (exige a senha atual).
     */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->input('password'));

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'Sessões em outros dispositivos foram encerradas.');
    }

    /**
     * Cria um token pessoal de acesso à API. O valor completo é exibido UMA
     * única vez; no banco fica apenas o hash.
     */
    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token_name' => ['required', 'string', 'max:100'],
            'expires_in_days' => ['nullable', 'integer', 'in:30,90,365'],
        ]);

        $expiresAt = isset($validated['expires_in_days']) ? now()->addDays((int) $validated['expires_in_days']) : null;
        $token = $request->user()->createToken($validated['token_name'], ['*'], $expiresAt);

        return back()->with('plain_token', $token->plainTextToken)->with('success', 'Token criado. Copie-o agora: ele não será exibido novamente.');
    }

    public function destroyToken(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->whereKey($tokenId)->delete();

        return back()->with('success', 'Token revogado.');
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Edge',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Navegador',
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Sistema desconhecido',
        };

        return "{$browser} em {$platform}";
    }
}
