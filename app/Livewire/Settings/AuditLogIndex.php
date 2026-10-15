<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithDataTable;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Consulta da trilha de auditoria da empresa ativa.
 *
 * AuditLog não usa o filtro multiempresa automático (alguns eventos não têm
 * empresa), então a consulta SEMPRE começa com forCompany($companyId).
 * A busca textual ($search, do WithDataTable) procura na descrição.
 */
#[Title('Auditoria')]
class AuditLogIndex extends Component
{
    use WithDataTable;

    /**
     * Eventos genéricos gravados automaticamente pelo trait Auditable.
     *
     * @var list<string>
     */
    private const array STANDARD_EVENTS = ['created', 'updated', 'deleted', 'restored'];

    #[Url(as: 'evento', except: '')]
    public string $event = '';

    #[Url(as: 'recurso', except: '')]
    public string $resourceType = '';

    #[Url(as: 'usuario', except: '')]
    public string $userId = '';

    #[Url(as: 'de', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'ate', except: '')]
    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorize('audit.view');
    }

    public function render(): View
    {
        $companyId = app(CurrentCompany::class)->getOrFail()->id;
        $search = trim($this->search);
        $dateFrom = $this->validDate($this->dateFrom);
        $dateTo = $this->validDate($this->dateTo);

        $logs = AuditLog::query()
            ->forCompany($companyId)
            ->with('user:id,name')
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', $this->event))
            ->when($this->resourceType !== '', fn (Builder $query) => $query->where('auditable_type', $this->resourceType))
            ->when(ctype_digit($this->userId), fn (Builder $query) => $query->where('user_id', (int) $this->userId))
            ->when($dateFrom !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($search !== '', fn (Builder $query) => $query->where('description', 'ilike', "%{$search}%"))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.settings.audit-log-index', [
            'logs' => $logs,
            'eventOptions' => $this->eventOptions($companyId),
            'resourceOptions' => $this->resourceOptions(),
            'userOptions' => User::query()
                ->whereHas('memberships', fn (Builder $query) => $query->where('company_id', $companyId))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ]);
    }

    /**
     * Eventos padrão + eventos de negócio já registrados para a empresa
     * (ex.: "proposal.approved", "payment.reversed").
     *
     * @return array<string, string>
     */
    private function eventOptions(int $companyId): array
    {
        $businessEvents = AuditLog::query()
            ->forCompany($companyId)
            ->whereNotIn('event', self::STANDARD_EVENTS)
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        return collect(self::STANDARD_EVENTS)
            ->merge($businessEvents)
            ->mapWithKeys(fn (string $event) => [$event => (new AuditLog)->forceFill(['event' => $event])->eventLabel()])
            ->all();
    }

    /**
     * Apelidos do morph map ("customer", "proposal", ...) com rótulos amigáveis.
     *
     * @return array<string, string>
     */
    private function resourceOptions(): array
    {
        return collect(Relation::morphMap())
            ->keys()
            ->mapWithKeys(fn (string $alias) => [$alias => config("morph-labels.{$alias}", $alias)])
            ->sort()
            ->all();
    }

    /**
     * Aceita apenas datas no formato do <input type="date"> (AAAA-MM-DD):
     * um valor arbitrário vindo da URL não pode chegar ao SQL.
     */
    private function validDate(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }
}
