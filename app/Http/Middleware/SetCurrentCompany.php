<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a EMPRESA ATIVA da requisição.
 *
 * - Web: usa users.current_company_id (escolhida no seletor de empresas).
 * - API: aceita o cabeçalho "X-Company-Id"; sem ele, usa a empresa atual do usuário.
 *
 * O usuário só pode ativar empresas às quais tem acesso (vínculo ativo e
 * empresa ativa). Este middleware roda ANTES do Route Model Binding
 * (veja bootstrap/app.php), para que o CompanyScope já filtre os models
 * carregados a partir da URL.
 */
class SetCurrentCompany
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $requestedCompanyId = $request->header('X-Company-Id');
        $companies = $user->accessibleCompanies();

        if ($requestedCompanyId !== null) {
            $company = $companies->firstWhere('id', (int) $requestedCompanyId);

            abort_if($company === null, Response::HTTP_FORBIDDEN, 'Você não tem acesso à empresa informada.');
        } else {
            $company = $companies->firstWhere('id', $user->current_company_id) ?? $companies->first();
        }

        if (! $company instanceof Company) {
            return $this->denyAccessWithoutCompany($request);
        }

        if ($requestedCompanyId === null && $user->current_company_id !== $company->id) {
            // saveQuietly(): atualiza sem disparar eventos (e sem gerar auditoria).
            $user->forceFill(['current_company_id' => $company->id])->saveQuietly();
        }

        $this->currentCompany->set($company);

        return $next($request);
    }

    private function denyAccessWithoutCompany(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Seu usuário não está vinculado a nenhuma empresa ativa.'], Response::HTTP_FORBIDDEN);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Seu usuário não está vinculado a nenhuma empresa ativa. Procure o administrador.',
        ]);
    }
}
