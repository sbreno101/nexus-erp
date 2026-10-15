<?php

namespace App\Livewire\Customers;

use App\Actions\Parties\DeleteBusinessParty;
use App\Enums\BillStatus;
use App\Enums\RecordStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Payable;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Fornecedores')]
class SupplierIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    /**
     * @var list<string>
     */
    protected array $sortable = ['name', 'document', 'created_at'];

    public function mount(): void
    {
        $this->authorize('viewAny', Supplier::class);
    }

    public function delete(int $supplierId, DeleteBusinessParty $deleteParty): void
    {
        $supplier = Supplier::query()->findOrFail($supplierId);
        $this->authorize('delete', $supplier);

        try {
            $deleteParty->handle($supplier);
            $this->dispatch('notify', type: 'success', message: 'Fornecedor excluído.');
        } catch (BusinessRuleException $exception) {
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());
        }
    }

    public function render(): View
    {
        // Subconsulta correlacionada: saldo em aberto (valor − pago) por fornecedor, em uma única query.
        $query = Supplier::query()
            ->select('suppliers.*')
            ->addSelect(['open_cents' => Payable::query()
                ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0)')
                ->whereColumn('payables.supplier_id', 'suppliers.id')
                ->whereIn('status', BillStatus::outstanding())])
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status));

        return view('livewire.customers.supplier-index', [
            'suppliers' => $this->applySorting($query, 'name')->paginate($this->perPageForCompany()),
            'statusOptions' => RecordStatus::options(),
        ]);
    }
}
