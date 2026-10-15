<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\CreateCompanyUser;
use App\Actions\Users\ToggleUserAccess;
use App\Actions\Users\UpdateCompanyUser;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\CompanyUnit;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cadastro e edição de usuários da empresa ativa (a listagem fica no
 * componente Livewire Settings\UserIndex).
 *
 * Senhas nunca são definidas por aqui: o novo usuário recebe um convite por
 * e-mail com um link para criar a própria senha.
 */
class UserController extends Controller
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('settings.users.create', [
            'user' => new User,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateCompanyUser $createUser): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->userData();
        $user = $createUser->handle($data);

        $message = match (true) {
            ! $user->wasRecentlyCreated => 'Usuário já possuía conta no sistema e foi vinculado à empresa.',
            $data['send_invitation'] => 'Usuário cadastrado. O convite para definir a senha foi enviado por e-mail.',
            default => 'Usuário cadastrado. Nenhum convite foi enviado.',
        };

        return redirect()->route('users.index')->with('success', $message);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $companyId = $this->currentCompany->id();
        $viewer = auth()->user();

        // Vínculo com a empresa ativa (perfil, unidade e situação).
        $membership = $user->memberships()
            ->where('company_id', $companyId)
            ->with(['role:id,name,slug', 'unit:id,name'])
            ->firstOrFail();

        // Outras empresas da pessoa: visível apenas ao superadministrador, para
        // não expor a um administrador dados de empresas que ele não gerencia.
        // O perfil de outra empresa precisa ignorar o filtro multiempresa.
        $otherMemberships = $viewer->isSuperAdmin()
            ? $user->memberships()
                ->where('company_id', '!=', $companyId)
                ->with([
                    'company:id,legal_name,trade_name,status',
                    'role' => fn ($query) => $query->withoutGlobalScopes()->select(['id', 'name']),
                ])
                ->get()
            : collect();

        $options = $this->formOptions();

        // Mantém no select a unidade atual mesmo que ela tenha sido inativada.
        if ($membership->unit !== null) {
            $options['units'][$membership->unit->id] ??= $membership->unit->name.' (inativa)';
        }

        return view('settings.users.edit', [
            'user' => $user,
            'membership' => $membership,
            'otherMemberships' => $otherMemberships,
            ...$options,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateCompanyUser $updateUser): RedirectResponse
    {
        $this->authorize('update', $user);

        $updateUser->handle($user, $request->userData());

        return redirect()->route('users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    /**
     * Ativa/desativa o acesso.
     *  - scope=company (padrão): vínculo com a empresa ativa.
     *  - scope=global: conta inteira (todas as empresas), só superadministrador.
     */
    public function toggleAccess(Request $request, User $user, ToggleUserAccess $toggleAccess): RedirectResponse
    {
        // Apenas dois campos simples: a validação inline dispensa um Form Request.
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            'scope' => ['nullable', Rule::in(['company', 'global'])],
        ]);

        $active = $request->boolean('active');

        if (($validated['scope'] ?? 'company') === 'global') {
            abort_unless($request->user()->isSuperAdmin(), 403);

            if ($user->is($request->user())) {
                throw new BusinessRuleException('Você não pode desativar a sua própria conta.');
            }

            $toggleAccess->globally($user, $active);

            return back()->with('success', $active ? 'Conta reativada em todas as empresas.' : 'Conta bloqueada em todas as empresas.');
        }

        $this->authorize('toggleStatus', $user);

        $toggleAccess->forCompany($user, $active);

        return back()->with('success', $active ? 'Acesso à empresa ativado.' : 'Acesso à empresa desativado.');
    }

    /**
     * Opções dos selects de perfil e unidade (ambos já filtrados pela
     * empresa ativa pelo CompanyScope).
     *
     * @return array{roles: array<int, string>, units: array<int, string>}
     */
    private function formOptions(): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->pluck('name', 'id')->all(),
            'units' => CompanyUnit::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
