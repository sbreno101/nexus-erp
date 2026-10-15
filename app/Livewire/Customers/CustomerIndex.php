<?php

namespace App\Livewire\Customers;

use App\Actions\Parties\DeleteBusinessParty;
use App\Enums\CustomerStatus;
use App\Enums\PersonType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Listagem de clientes com busca, filtros, ordenação e paginação em tempo real.
 *
 * Fluxo: o usuário digita na busca → o Livewire envia uma requisição AJAX →
 * a propriedade $search é atualizada → render() executa a consulta de novo
 * → o HTML da tabela é atualizado na tela (sem recarregar a página).
 */
#[Title('Clientes')]
class CustomerIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $type = '';

    /**
     * @var list<string>
     */
    protected array $sortable = ['name', 'document', 'created_at', 'status'];

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    public function delete(int $customerId, DeleteBusinessParty $deleteParty): void
    {
        $customer = Customer::query()->findOrFail($customerId);
        $this->authorize('delete', $customer);

        try {
            $deleteParty->handle($customer);
            $this->dispatch('notify', type: 'success', message: 'Cliente excluído.');
        } catch (BusinessRuleException $exception) {
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());
        }
    }

    public function render(): View
    {
        $query = Customer::query()
            ->with('owner:id,name')
            ->withCount(['contracts as active_contracts_count' => fn ($query) => $query->where('status', 'active')])
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type));

        return view('livewire.customers.customer-index', [
            'customers' => $this->applySorting($query, 'name')->paginate($this->perPageForCompany()),
            'statusOptions' => CustomerStatus::options(),
            'typeOptions' => PersonType::options(),
        ]);
    }
}
