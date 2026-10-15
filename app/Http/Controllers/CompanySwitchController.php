<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Troca a empresa ativa do usuário. Só aceita empresas às quais ele tem acesso.
 */
class CompanySwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['company_id' => ['required', 'integer']]);
        $user = $request->user();

        abort_unless($user->canAccessCompany((int) $validated['company_id']), 403, 'Você não tem acesso a esta empresa.');

        $user->forceFill(['current_company_id' => (int) $validated['company_id']])->saveQuietly();
        $company = $user->accessibleCompanies()->firstWhere('id', (int) $validated['company_id']);

        // Volta ao dashboard: a página anterior pode exibir um registro da empresa antiga.
        return redirect()->route('dashboard')->with('success', "Empresa ativa: {$company->display_name}.");
    }
}
