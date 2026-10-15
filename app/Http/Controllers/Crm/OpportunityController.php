<?php

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\SaveOpportunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\OpportunityRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function create(Request $request): View
    {
        $this->authorize('create', Opportunity::class);

        $firstStage = PipelineStage::query()->ordered()->where('is_won', false)->where('is_lost', false)->first();

        return view('crm.opportunities.create', [
            'opportunity' => new Opportunity([
                'customer_id' => $request->integer('customer_id') ?: null,
                'lead_id' => $request->integer('lead_id') ?: null,
                'pipeline_stage_id' => $request->integer('stage_id') ?: $firstStage?->id,
                'owner_id' => auth()->id(),
                'estimated_value_cents' => 0,
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(OpportunityRequest $request, SaveOpportunity $save): RedirectResponse
    {
        $this->authorize('create', Opportunity::class);

        $opportunity = $save->create($request->safe()->except('estimated_value'));

        return redirect()->route('opportunities.show', $opportunity)->with('success', 'Oportunidade criada.');
    }

    public function show(Opportunity $opportunity): View
    {
        $this->authorize('view', $opportunity);

        $opportunity->load([
            'customer', 'lead', 'stage', 'owner',
            'interactions.user', 'tasks.assignee', 'statusChanges.user',
            'proposals' => fn ($query) => $query->latest(),
        ]);

        return view('crm.opportunities.show', [
            'opportunity' => $opportunity,
            'users' => User::query()->active()->memberOf($this->currentCompany->id())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Opportunity $opportunity): View
    {
        $this->authorize('update', $opportunity);

        return view('crm.opportunities.edit', ['opportunity' => $opportunity, ...$this->formOptions()]);
    }

    public function update(OpportunityRequest $request, Opportunity $opportunity, SaveOpportunity $save): RedirectResponse
    {
        $this->authorize('update', $opportunity);

        $save->update($opportunity, $request->safe()->except('estimated_value'));

        return redirect()->route('opportunities.show', $opportunity)->with('success', 'Oportunidade atualizada.');
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('delete', $opportunity);

        $opportunity->delete();

        return redirect()->route('opportunities.pipeline')->with('success', 'Oportunidade excluída.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'trade_name'])->mapWithKeys(fn ($customer) => [$customer->id => $customer->display_name])->all(),
            'leads' => Lead::query()->where('status', '!=', 'converted')->orderBy('name')->get(['id', 'name', 'company_name'])
                ->mapWithKeys(fn ($lead) => [$lead->id => $lead->name.($lead->company_name ? " ({$lead->company_name})" : '')])->all(),
            'stages' => PipelineStage::query()->ordered()->pluck('name', 'id')->all(),
            'users' => User::optionsForCompany($this->currentCompany->id()),
        ];
    }
}
