<?php

namespace App\Livewire\Settings;

use App\Actions\Users\ToggleUserAccess;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Listagem dos usuários vinculados à empresa ativa.
 *
 * User NÃO é um model multiempresa (uma pessoa pode estar em várias
 * empresas), então o filtro pela empresa é feito explicitamente pelo
 * vínculo (tabela company_user, model CompanyMembership).
 */
#[Title('Usuários')]
class UserIndex extends Component
{
    use WithDataTable;

    #[Url(as: 'perfil', except: '')]
    public string $role = '';

    /**
     * active = vínculo ativo; inactive = vínculo desativado; blocked = conta global bloqueada.
     */
    #[Url(as: 'situacao', except: '')]
    public string $status = '';

    /**
     * @var list<string>
     */
    protected array $sortable = ['name', 'email', 'last_login_at'];

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * Inverte o acesso do usuário à empresa ativa (ativo <-> inativo).
     */
    public function toggleAccess(int $userId, ToggleUserAccess $toggleAccess): void
    {
        $companyId = $this->companyId();

        $user = User::query()
            ->whereHas('memberships', fn (Builder $query) => $query->where('company_id', $companyId))
            ->with(['memberships' => fn ($query) => $query->where('company_id', $companyId)])
            ->findOrFail($userId);

        $this->authorize('toggleStatus', $user);

        $activate = ! $user->memberships->first()->is_active;

        try {
            $toggleAccess->forCompany($user, $activate);
            $this->dispatch('notify', type: 'success', message: $activate ? "Acesso de {$user->name} ativado." : "Acesso de {$user->name} desativado.");
        } catch (BusinessRuleException $exception) {
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());
        }
    }

    public function render(): View
    {
        $companyId = $this->companyId();
        $search = trim($this->search);

        $query = User::query()
            ->whereHas('memberships', fn (Builder $membership) => $membership
                ->where('company_id', $companyId)
                ->when($this->role !== '', fn (Builder $query) => $query->where('role_id', (int) $this->role))
                ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false)))
            ->when($this->status === 'blocked', fn (Builder $query) => $query->where('is_active', false))
            ->when($search !== '', fn (Builder $query) => $query->whereAny(['name', 'email'], 'ilike', "%{$search}%"))
            // Eager loading só do vínculo com a empresa ativa (evita N+1 e
            // não carrega dados de outras empresas).
            ->with(['memberships' => fn ($query) => $query
                ->where('company_id', $companyId)
                ->with(['role:id,name,slug', 'unit:id,name'])]);

        return view('livewire.settings.user-index', [
            'users' => $this->applySorting($query, 'name')->paginate($this->perPageForCompany()),
            'roleOptions' => Role::query()->orderBy('name')->pluck('name', 'id')->all(),
            'statusOptions' => [
                'active' => 'Acesso ativo',
                'inactive' => 'Acesso desativado',
                'blocked' => 'Conta bloqueada (global)',
            ],
        ]);
    }

    private function companyId(): int
    {
        return app(CurrentCompany::class)->getOrFail()->id;
    }
}
