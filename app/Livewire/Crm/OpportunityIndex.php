<?php

namespace App\Livewire\Crm;

use App\Enums\OpportunityStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Oportunidades em formato de tabela (alternativa ao Kanban).
 */
#[Title('Oportunidades')]
class OpportunityIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'etapa', except: '')]
    public string $stage = '';

    #[Url(as: 'responsavel', except: '')]
    public string $owner = '';

    /**
     * @var list<string>
     */
    protected array $sortable = ['title', 'estimated_value_cents', 'probability', 'expected_close_date', 'created_at'];

    public function mount(): void
    {
        $this->authorize('viewAny', Opportunity::class);
    }

    public function render(): View
    {
        $query = Opportunity::query()
            ->with(['stage:id,name,color', 'customer:id,name,trade_name', 'lead:id,name,company_name', 'owner:id,name'])
            ->when(trim($this->search) !== '', fn ($query) => $query->where('title', 'ilike', '%'.trim($this->search).'%'))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->stage !== '', fn ($query) => $query->where('pipeline_stage_id', $this->stage))
            ->when($this->owner === 'me', fn ($query) => $query->where('owner_id', auth()->id()))
            ->when(ctype_digit($this->owner), fn ($query) => $query->where('owner_id', $this->owner));

        $totals = (clone $query)->reorder()->selectRaw('COALESCE(SUM(estimated_value_cents), 0) AS total, COALESCE(SUM(estimated_value_cents * probability / 100), 0) AS weighted')->first();

        return view('livewire.crm.opportunity-index', [
            'opportunities' => $this->applySorting($query, 'expected_close_date')->paginate($this->perPageForCompany()),
            'totals' => $totals,
            'statusOptions' => OpportunityStatus::options(),
            'stages' => PipelineStage::query()->ordered()->pluck('name', 'id'),
            'owners' => User::optionsForCompany(app(CurrentCompany::class)->id()),
        ]);
    }
}
