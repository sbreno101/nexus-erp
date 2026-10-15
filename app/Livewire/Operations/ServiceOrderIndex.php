<?php

namespace App\Livewire\Operations;

use App\Enums\Priority;
use App\Enums\ServiceOrderStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Operations\Concerns\SortsByPriority;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Listagem de ordens de serviço com filtros por status, prioridade,
 * responsável e atraso.
 */
#[Title('Ordens de serviço')]
class ServiceOrderIndex extends Component
{
    use SortsByPriority;
    use WithDataTable;

    /**
     * Valor especial do filtro de status: todas as OS ainda não finalizadas.
     */
    public const string PENDING_FILTER = 'pendentes';

    /**
     * Valores especiais do filtro de responsável.
     */
    public const string MINE_FILTER = 'minhas';

    public const string UNASSIGNED_FILTER = 'sem-responsavel';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'prioridade', except: '')]
    public string $priority = '';

    #[Url(as: 'responsavel', except: '')]
    public string $assignee = '';

    #[Url(as: 'atrasadas', except: false)]
    public bool $onlyOverdue = false;

    /**
     * @var list<string>
     */
    protected array $sortable = ['number', 'due_date', 'priority', 'created_at'];

    public function mount(): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
    }

    public function render(): View
    {
        $query = ServiceOrder::query()
            ->with(['customer:id,name,trade_name', 'assignee:id,name'])
            ->search($this->search)
            ->when($this->status === self::PENDING_FILTER, fn ($query) => $query->pending())
            ->when(ServiceOrderStatus::tryFrom($this->status), fn ($query, ServiceOrderStatus $status) => $query->where('status', $status))
            ->when(Priority::tryFrom($this->priority), fn ($query, Priority $priority) => $query->where('priority', $priority))
            ->when($this->assignee === self::MINE_FILTER, fn ($query) => $query->where('assigned_to', Auth::id()))
            ->when($this->assignee === self::UNASSIGNED_FILTER, fn ($query) => $query->whereNull('assigned_to'))
            ->when(ctype_digit($this->assignee), fn ($query) => $query->where('assigned_to', (int) $this->assignee))
            // Atrasada = ainda pendente e com prazo anterior a hoje
            // (mesma regra do método ServiceOrder::isOverdue()).
            ->when($this->onlyOverdue, fn ($query) => $query->pending()->whereDate('due_date', '<', today()));

        return view('livewire.operations.service-order-index', [
            'serviceOrders' => $this->applySortingWithPriority($query, 'created_at', 'desc')->paginate($this->perPageForCompany()),
            'statusOptions' => ServiceOrderStatus::options(),
            'priorityOptions' => Priority::options(),
            'users' => User::optionsForCompany(app(CurrentCompany::class)->id()),
        ]);
    }
}
