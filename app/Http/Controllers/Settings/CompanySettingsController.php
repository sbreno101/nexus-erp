<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Companies\UpdateCompanySettings;
use App\Enums\ThemePreference;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanySettingsRequest;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Configurações da EMPRESA ATIVA (dados institucionais, logotipo,
 * preferências e parâmetros operacionais).
 *
 * Não há {company} na URL: o administrador sempre edita a empresa em que
 * está trabalhando, o que elimina o risco de editar outra empresa trocando
 * o id no endereço.
 */
class CompanySettingsController extends Controller
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function edit(): View
    {
        $company = $this->currentCompany->getOrFail();
        $this->authorize('update', $company);

        return view('settings.company', [
            'company' => $company,
            'settings' => $company->settingsOrDefault(),
            'themeOptions' => ThemePreference::options(),
        ]);
    }

    public function update(CompanySettingsRequest $request, UpdateCompanySettings $updateSettings): RedirectResponse
    {
        $company = $this->currentCompany->getOrFail();
        $this->authorize('update', $company);

        $updateSettings->handle(
            $company,
            $request->companyData(),
            $request->settingsData(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return redirect()->route('settings.company.edit')->with('success', 'Configurações da empresa salvas com sucesso.');
    }
}
