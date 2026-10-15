<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Companies\DeleteCompanyUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UnitRequest;
use App\Models\CompanyUnit;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Unidades (filiais/escritórios) da empresa ativa.
 *
 * CompanyUnit usa o trait BelongsToCompany: a consulta e o route model
 * binding já ficam restritos à empresa ativa, e o company_id é preenchido
 * automaticamente ao criar.
 */
class UnitController extends Controller
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function index(): View
    {
        $this->authorize('viewAny', CompanyUnit::class);

        $perPage = $this->currentCompany->get()?->settingsOrDefault()->items_per_page ?? 15;

        return view('settings.units.index', [
            'units' => CompanyUnit::query()
                ->withCount(['memberships', 'contracts'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate($perPage),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CompanyUnit::class);

        return view('settings.units.create', [
            'unit' => new CompanyUnit(['is_active' => true]),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $this->authorize('create', CompanyUnit::class);

        $unit = CompanyUnit::create($request->unitData());

        return redirect()->route('units.index')->with('success', "Unidade {$unit->name} cadastrada com sucesso.");
    }

    public function edit(CompanyUnit $unit): View
    {
        $this->authorize('update', $unit);

        return view('settings.units.edit', [
            'unit' => $unit,
        ]);
    }

    public function update(UnitRequest $request, CompanyUnit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $unit->update($request->unitData());

        return redirect()->route('units.index')->with('success', "Unidade {$unit->name} atualizada com sucesso.");
    }

    public function destroy(CompanyUnit $unit, DeleteCompanyUnit $deleteUnit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $deleteUnit->handle($unit);

        return redirect()->route('units.index')->with('success', 'Unidade excluída.');
    }
}
