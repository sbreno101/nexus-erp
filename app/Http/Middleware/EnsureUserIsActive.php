<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encerra imediatamente a sessão de um usuário desativado.
 *
 * O login já bloqueia contas inativas (FortifyServiceProvider), mas uma
 * conta pode ser desativada enquanto o usuário está navegando: este
 * middleware garante que a próxima requisição dele seja recusada.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Sua conta está desativada.'], Response::HTTP_FORBIDDEN);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => 'Sua conta está desativada. Procure o administrador.']);
    }
}
