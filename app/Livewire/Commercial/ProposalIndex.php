<?php

namespace App\Livewire\Commercial;

use App\Enums\ProposalStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Propostas')]
class ProposalIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'responsavel', except: '')]
    public string $owner = '';

    #[Url(as: 'de', except: '')]
    public string $from = '';

    #[Url(as: 'ate', except: '')]
    public string $to = '';

    /**
     * @var list<string>
     */
    protected array $sortable = ['number', 'issue_date', 'valid_until', 'total_cents'];

    public function mount(): void
    {
        $this->authorize('viewAny', Proposal::class);
    }

    public function render(): View
    {
        $query = Proposal::query()
            ->with(['customer:id,name,trade_name', 'owner:id,name'])
            ->search($this->search)
            ->when($this->status === 'open', fn ($query) => $query->whereIn('status', ProposalStatus::open()))
            ->when($this->status !== '' && $this->status !== 'open', fn ($query) => $query->where('status', $this->status))
            ->when($this->owner === 'me', fn ($query) => $query->where('owner_id', auth()->id()))
            ->when(ctype_digit($this->owner), fn ($query) => $query->where('owner_id', $this->owner))
            ->when($this->from !== '', fn ($query) => $query->whereDate('issue_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('issue_date', '<=', $this->to));

        $summary = (clone $query)->reorder()->selectRaw('COUNT(*) AS total, COALESCE(SUM(total_cents), 0) AS amount')->first();

        return view('livewire.commercial.proposal-index', [
            'proposals' => $this->applySorting($query, 'issue_date', 'desc')->paginate($this->perPageForCompany()),
            'summary' => $summary,
            'statusOptions' => ['open' => 'Em aberto (rascunho + enviada)', ...ProposalStatus::options()],
            'owners' => User::optionsForCompany(app(CurrentCompany::class)->id()),
        ]);
    }
}
