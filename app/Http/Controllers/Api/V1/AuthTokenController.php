<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Emissão e revogação de tokens de acesso (Laravel Sanctum).
 *
 * POST /api/v1/auth/token   {email, password, device_name}  → {token, ...}
 * DELETE /api/v1/auth/token                                  → revoga o token atual
 *
 * Limitado a 5 tentativas por minuto por e-mail + IP (proteção contra força bruta).
 */
class AuthTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', mb_strtolower($credentials['email']))->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Sua conta está desativada.']);
        }

        if ($user->accessibleCompanies()->isEmpty()) {
            throw ValidationException::withMessages(['email' => 'Seu usuário não está vinculado a nenhuma empresa ativa.']);
        }

        $token = $user->createToken($credentials['device_name'], ['*'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ], 201);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token revogado.']);
    }
}
