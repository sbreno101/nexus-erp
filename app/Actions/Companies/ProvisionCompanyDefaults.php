<?php

namespace App\Actions\Companies;

use App\Enums\CatalogItemType;
use App\Enums\FinancialCategoryType;
use App\Models\CatalogCategory;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\FinancialCategory;
use App\Models\LeadSource;
use App\Models\Permission;
use App\Models\PipelineStage;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Support\Tenancy\CurrentCompany;

/**
 * Cria a configuração inicial de uma nova empresa: perfis de acesso padrão
 * (config/permissions.php), etapas do funil, origens de leads, categorias
 * financeiras, centros de custo e categorias de chamados e do catálogo.
 *
 * Idempotente: pode ser executada novamente sem duplicar registros
 * (usa firstOrCreate / updateOrCreate).
 */
class ProvisionCompanyDefaults
{
    public function __construct(private CurrentCompany $currentCompany) {}

    public function handle(Company $company): void
    {
        $this->currentCompany->run($company, function (Company $company): void {
            $company->settingsOrDefault();
            $this->provisionRoles($company);
            $this->provisionCrm();
            $this->provisionFinance();
            $this->provisionOperations();
        });
    }

    private function provisionRoles(Company $company): void
    {
        $allPermissionIds = Permission::query()->pluck('id', 'name');

        foreach (config('permissions.roles') as $slug => $definition) {
            $role = Role::query()->firstOrCreate(
                ['company_id' => $company->id, 'slug' => $slug],
                ['name' => $definition['name'], 'description' => $definition['description'], 'is_system' => true],
            );

            $permissionIds = $definition['permissions'] === ['*']
                ? $allPermissionIds->values()
                : $allPermissionIds->only($definition['permissions'])->values();

            // Na primeira criação aplica o padrão; perfis já existentes mantêm
            // as customizações da empresa (exceto o administrador, sempre completo).
            if ($role->wasRecentlyCreated || $role->slug === Role::COMPANY_ADMIN) {
                $role->permissions()->sync($permissionIds);
            }
        }
    }

    private function provisionCrm(): void
    {
        $stages = [
            ['Prospecção', 10, '#64748b', false, false],
            ['Qualificação', 25, '#3b82f6', false, false],
            ['Proposta enviada', 50, '#8b5cf6', false, false],
            ['Negociação', 75, '#f59e0b', false, false],
            ['Ganha', 100, '#10b981', true, false],
            ['Perdida', 0, '#ef4444', false, true],
        ];

        foreach ($stages as $position => [$name, $probability, $color, $isWon, $isLost]) {
            PipelineStage::query()->firstOrCreate(['name' => $name], [
                'position' => $position,
                'probability' => $probability,
                'color' => $color,
                'is_won' => $isWon,
                'is_lost' => $isLost,
            ]);
        }

        foreach (['Site', 'Indicação', 'Redes sociais', 'Evento', 'Prospecção ativa', 'Parceiro'] as $source) {
            LeadSource::query()->firstOrCreate(['name' => $source]);
        }
    }

    private function provisionFinance(): void
    {
        $categories = [
            FinancialCategoryType::Income->value => ['Serviços recorrentes', 'Projetos e implantação', 'Consultoria', 'Venda de produtos', 'Outras receitas'],
            FinancialCategoryType::Expense->value => ['Folha de pagamento', 'Aluguel e condomínio', 'Softwares e assinaturas', 'Impostos e taxas', 'Marketing', 'Fornecedores de serviços', 'Tarifas bancárias', 'Outras despesas'],
        ];

        foreach ($categories as $type => $names) {
            foreach ($names as $name) {
                FinancialCategory::query()->firstOrCreate(['type' => $type, 'name' => $name]);
            }
        }

        foreach (['ADM' => 'Administrativo', 'COM' => 'Comercial', 'OPS' => 'Operações', 'TI' => 'Tecnologia'] as $code => $name) {
            CostCenter::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    private function provisionOperations(): void
    {
        foreach (['Dúvida', 'Problema técnico', 'Solicitação', 'Financeiro', 'Reclamação'] as $name) {
            TicketCategory::query()->firstOrCreate(['name' => $name]);
        }

        $catalogCategories = [
            CatalogItemType::Service->value => ['Consultoria', 'Suporte', 'Implantação', 'Treinamento'],
            CatalogItemType::Product->value => ['Licenças', 'Equipamentos'],
        ];

        foreach ($catalogCategories as $type => $names) {
            foreach ($names as $name) {
                CatalogCategory::query()->firstOrCreate(['type' => $type, 'name' => $name]);
            }
        }
    }
}
