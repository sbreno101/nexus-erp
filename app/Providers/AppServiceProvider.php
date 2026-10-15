<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanySetting;
use App\Models\CompanyUnit;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Proposal;
use App\Models\Receivable;
use App\Models\RecurringExpense;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\Transfer;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // "scoped": uma instância por requisição/job, descartada ao final.
        $this->app->scoped(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureAuthorization();
        $this->configureRateLimiting();

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());
    }

    private function configureModels(): void
    {
        // Em desenvolvimento, falha ao acessar relacionamentos não carregados
        // (lazy loading): ajuda a detectar problemas de N+1 queries cedo.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Apelidos curtos gravados nas colunas *_type das relações polimórficas,
        // em vez do nome completo da classe (desacopla o banco do código).
        Relation::enforceMorphMap([
            'company' => Company::class,
            'company_setting' => CompanySetting::class,
            'company_unit' => CompanyUnit::class,
            'company_membership' => CompanyMembership::class,
            'role' => Role::class,
            'user' => User::class,
            'customer' => Customer::class,
            'supplier' => Supplier::class,
            'lead' => Lead::class,
            'opportunity' => Opportunity::class,
            'catalog_item' => CatalogItem::class,
            'proposal' => Proposal::class,
            'contract' => Contract::class,
            'service_order' => ServiceOrder::class,
            'ticket' => Ticket::class,
            'financial_account' => FinancialAccount::class,
            'recurring_expense' => RecurringExpense::class,
            'receivable' => Receivable::class,
            'payable' => Payable::class,
            'payment' => Payment::class,
            'transfer' => Transfer::class,
            'financial_transaction' => FinancialTransaction::class,
            'attachment' => Attachment::class,
        ]);
    }

    private function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Habilidades no formato "recurso.acao" sem model associado
            // (ex.: @can('reports.financial')) são verificadas direto na
            // lista de permissões do perfil do usuário na empresa ativa.
            if ($arguments === [] && str_contains($ability, '.')) {
                return $user->hasPermission($ability);
            }

            return null;
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn () => response()->json([
                'message' => 'Muitas requisições. Aguarde alguns instantes e tente novamente.',
            ], 429)));

        RateLimiter::for('api-token', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn () => response()->json([
                'message' => 'Muitas tentativas de login. Aguarde um minuto e tente novamente.',
            ], 429)));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
