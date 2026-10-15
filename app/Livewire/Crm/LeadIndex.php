<?php

namespace App\Livewire\Crm;

use App\Enums\LeadStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Leads')]
class LeadIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'origem', except: '')]
    public string $source = '';

    #[Url(as: 'responsavel', except: '')]
    public string $owner = '';

    /**
     * @var list<string>
     */
    protected array $sortable = ['name', 'created_at', 'estimated_value_cents', 'next_contact_at'];

    public function mount(): void
    {
        $this->authorize('viewAny', Lead::class);
    }

    public function render(): View
    {
        $query = Lead::query()
            ->with(['source:id,name', 'owner:id,name'])
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->source !== '', fn ($query) => $query->where('lead_source_id', $this->source))
            ->when($this->owner === 'me', fn ($query) => $query->where('owner_id', auth()->id()))
            ->when(ctype_digit($this->owner), fn ($query) => $query->where('owner_id', $this->owner));

        return view('livewire.crm.lead-index', [
            'leads' => $this->applySorting($query, 'created_at', 'desc')->paginate($this->perPageForCompany()),
            'statusOptions' => LeadStatus::options(),
            'sources' => LeadSource::query()->orderBy('name')->pluck('name', 'id'),
            'owners' => User::optionsForCompany(app(CurrentCompany::class)->id()),
        ]);
    }
}
