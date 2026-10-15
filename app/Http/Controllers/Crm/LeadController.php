<?php

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\ConvertLeadToCustomer;
use App\Enums\LeadStatus;
use App\Enums\PersonType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ConvertLeadRequest;
use App\Http\Requests\Crm\LeadRequest;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Cadastro e conversão de leads (a listagem fica no Livewire LeadIndex).
 */
class LeadController extends Controller
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('crm.leads.create', [
            'lead' => new Lead(['status' => LeadStatus::New, 'owner_id' => auth()->id()]),
            ...$this->formOptions(),
        ]);
    }

    public function store(LeadRequest $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $lead = Lead::create($request->leadData());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead cadastrado.');
    }

    public function show(Lead $lead): View
    {
        $this->authorize('view', $lead);

        $lead->load([
            'source', 'owner', 'customer',
            'interactions.user', 'tasks.assignee',
            'opportunities' => fn ($query) => $query->with('stage')->latest(),
        ]);

        return view('crm.leads.show', [
            'lead' => $lead,
            'users' => User::query()->active()->memberOf($this->currentCompany->id())->orderBy('name')->get(['id', 'name']),
            'auditLogs' => $lead->auditLogs()->with('user')->limit(15)->get(),
        ]);
    }

    public function edit(Lead $lead): View
    {
        $this->authorize('update', $lead);

        return view('crm.leads.edit', ['lead' => $lead, ...$this->formOptions()]);
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $lead->update($request->leadData());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead atualizado.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'Lead excluído.');
    }

    public function convertForm(Lead $lead): View
    {
        $this->authorize('convert', $lead);

        return view('crm.leads.convert', [
            'lead' => $lead,
            'users' => User::optionsForCompany($this->currentCompany->id()),
            'suggestedType' => strlen((string) $lead->document) === 11 || $lead->company_name === null ? PersonType::Individual : PersonType::Company,
        ]);
    }

    public function convert(ConvertLeadRequest $request, Lead $lead, ConvertLeadToCustomer $convert): RedirectResponse
    {
        $this->authorize('convert', $lead);

        $customer = $convert->handle($lead, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Lead convertido em cliente com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'sources' => LeadSource::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::optionsForCompany($this->currentCompany->id()),
            'statusOptions' => collect(LeadStatus::options())->except(LeadStatus::Converted->value)->all(),
        ];
    }
}
