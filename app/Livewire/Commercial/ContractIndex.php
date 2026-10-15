<?php

namespace App\Livewire\Commercial;

use App\Enums\BillingFrequency;
use App\Enums\ContractStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Contract;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Contratos')]
class ContractIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'periodicidade', except: '')]
    public string $frequency = '';

    /**
     * Somente contratos com término nos próximos 60 dias.
     */
    #[Url(as: 'a_vencer', except: false)]
    public bool $expiring = false;

    /**
     * @var list<string>
     */
    protected array $sortable = ['number', 'start_date', 'end_date', 'amount_cents', 'next_billing_date'];

    public function mount(): void
    {
        $this->authorize('viewAny', Contract::class);
    }

    public function render(): View
    {
        $query = Contract::query()
            ->with(['customer:id,name,trade_name'])
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->frequency !== '', fn ($query) => $query->where('billing_frequency', $this->frequency))
            ->when($this->expiring, fn ($query) => $query->whereNotNull('end_date')->whereBetween('end_date', [today(), today()->addDays(60)]));

        return view('livewire.commercial.contract-index', [
            'contracts' => $this->applySorting($query, 'number', 'desc')->paginate($this->perPageForCompany()),
            'statusOptions' => ContractStatus::options(),
            'frequencyOptions' => BillingFrequency::options(),
        ]);
    }
}
