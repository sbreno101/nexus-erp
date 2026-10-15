<?php

use App\Actions\Companies\ProvisionCompanyDefaults;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Role;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configuração do Pest
|--------------------------------------------------------------------------
| Todos os testes de Feature e Unit usam o TestCase do Laravel e o trait
| RefreshDatabase: o banco nexus_erp_testing é migrado uma vez e cada teste
| roda dentro de uma transação desfeita ao final (testes isolados).
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Cria uma empresa com a configuração padrão (perfis, funil, categorias...).
 *
 * @param  array<string, mixed>  $attributes
 */
function createCompany(array $attributes = []): Company
{
    $company = Company::factory()->create($attributes);
    app(ProvisionCompanyDefaults::class)->handle($company);

    return $company->refresh();
}

/**
 * Cria um usuário vinculado à empresa com o perfil informado
 * (slugs de config/permissions.php: company-admin, manager, sales, finance, support, collaborator).
 *
 * @param  array<string, mixed>  $attributes
 */
function createUserWithRole(Company $company, string $roleSlug = 'company-admin', array $attributes = []): User
{
    $user = User::factory()->create(['current_company_id' => $company->id, ...$attributes]);

    $role = Role::query()->withoutGlobalScope(CompanyScope::class)
        ->where('company_id', $company->id)
        ->where('slug', $roleSlug)
        ->firstOrFail();

    CompanyMembership::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    return $user;
}

/**
 * Autentica um usuário com o perfil informado e define a empresa ativa.
 * Retorna o usuário; a empresa fica acessível via currentCompany().
 */
function actingAsRole(string $roleSlug = 'company-admin', ?Company $company = null): User
{
    $company ??= createCompany();
    $user = createUserWithRole($company, $roleSlug);

    test()->actingAs($user);
    setCurrentCompany($company);

    return $user;
}

/**
 * Define a empresa ativa para código executado fora de uma requisição HTTP
 * (Actions chamadas diretamente nos testes).
 */
function setCurrentCompany(Company $company): void
{
    app(CurrentCompany::class)->set($company);
}

function currentCompany(): Company
{
    return app(CurrentCompany::class)->getOrFail();
}
