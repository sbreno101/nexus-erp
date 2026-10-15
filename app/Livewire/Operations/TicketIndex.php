<?php

namespace App\Livewire\Operations;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Operations\Concerns\SortsByPriority;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Listagem de chamados de atendimento com filtros por status, prioridade,
 * categoria e responsável.
 */
#[Title('Chamados')]
class TicketIndex extends Component
{
    use SortsByPriority;
    use WithDataTable;

    /**
     * Valor especial do filtro de status: chamados ainda em aberto
     * (Aberto, Em atendimento e Aguardando cliente). Não pode ser "open",
     * pois esse já é o valor do status "Aberto".
     */
    public const string OPEN_FILTER = 'em-aberto';

    /**
     * Valores especiais do filtro de responsável.
     */
    public const string MINE_FILTER = 'meus';

    public const string UNASSIGNED_FILTER = 'sem-responsavel';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'prioridade', except: '')]
    public string $priority = '';

    #[Url(as: 'categoria', except: '')]
    public string $category = '';

    #[Url(as: 'responsavel', except: '')]
    public string $assignee = '';

    /**
     * @var list<string>
     */
    protected array $sortable = ['number', 'created_at', 'priority'];

    public function mount(): void
    {
        $this->authorize('viewAny', Ticket::class);
    }

    public function render(): View
    {
        $query = Ticket::query()
            ->with(['customer:id,name,trade_name', 'assignee:id,name', 'category:id,name'])
            ->search($this->search)
            ->when($this->status === self::OPEN_FILTER, fn ($query) => $query->open())
            ->when(TicketStatus::tryFrom($this->status), fn ($query, TicketStatus $status) => $query->where('status', $status))
            ->when(Priority::tryFrom($this->priority), fn ($query, Priority $priority) => $query->where('priority', $priority))
            ->when(ctype_digit($this->category), fn ($query) => $query->where('ticket_category_id', (int) $this->category))
            ->when($this->assignee === self::MINE_FILTER, fn ($query) => $query->where('assigned_to', Auth::id()))
            ->when($this->assignee === self::UNASSIGNED_FILTER, fn ($query) => $query->whereNull('assigned_to'))
            ->when(ctype_digit($this->assignee), fn ($query) => $query->where('assigned_to', (int) $this->assignee));

        return view('livewire.operations.ticket-index', [
            'tickets' => $this->applySortingWithPriority($query, 'created_at', 'desc')->paginate($this->perPageForCompany()),
            'statusOptions' => TicketStatus::options(),
            'priorityOptions' => Priority::options(),
            'categoryOptions' => TicketCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::optionsForCompany(app(CurrentCompany::class)->id()),
        ]);
    }
}
