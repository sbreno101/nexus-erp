<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Companies\CreateCompany;
use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\Company;
use App\Support\BrazilianDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cadastro de empresas da plataforma (somente superadministrador).
 *
 * Autorização: a CompanyPolicy NEGA viewAny/create a todos e o Gate::before
 * libera o superadministrador. Em edit/update também usamos "viewAny" (e não
 * "update"), porque CompanyPolicy::update existe para a tela de
 * configurações e liberaria o administrador da própria empresa — que não
 * pode, por exemplo, alterar o status cadastral dela.
 *
 * Company não usa o filtro multiempresa: aqui enxergamos TODAS as empresas.
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Company::class);

        $search = trim((string) $request->query('busca'));
        $status = (string) $request->query('status');
        $cnpjSearch = BrazilianDocument::clean($search);

        $companies = Company::query()
            ->withCount('memberships')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereAny(['legal_name', 'trade_name', 'email'], 'ilike', "%{$search}%")
                ->when($cnpjSearch !== '', fn ($query) => $query->orWhere('cnpj', 'like', "%{$cnpjSearch}%"))))
            ->when(CompanyStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->orderBy('legal_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.companies.index', [
            'companies' => $companies,
            'search' => $search,
            'status' => $status,
            'statusOptions' => CompanyStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Company::class);

        return view('admin.companies.create', [
            'company' => new Company(['status' => CompanyStatus::Active]),
            'statusOptions' => CompanyStatus::options(),
        ]);
    }

    /**
     * A Action CreateCompany também provisiona os perfis de acesso e os
     * cadastros padrão da nova empresa.
     */
    public function store(CompanyRequest $request, CreateCompany $createCompany): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $company = $createCompany->handle($request->validated());

        return redirect()->route('admin.companies.index')
            ->with('success', "Empresa {$company->display_name} cadastrada. Selecione-a no seletor de empresas para cadastrar os usuários.");
    }

    public function edit(Company $company): View
    {
        $this->authorize('viewAny', Company::class);

        return view('admin.companies.edit', [
            'company' => $company->loadCount('memberships'),
            'statusOptions' => CompanyStatus::options(),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('viewAny', Company::class);

        $company->update($request->validated());

        return redirect()->route('admin.companies.index')->with('success', "Empresa {$company->display_name} atualizada com sucesso.");
    }
}
