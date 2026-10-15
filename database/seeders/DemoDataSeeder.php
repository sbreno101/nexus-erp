<?php

namespace Database\Seeders;

use App\Actions\Companies\CreateCompany;
use App\Actions\Contracts\ChangeContractStatus;
use App\Actions\Contracts\GenerateContractCharges;
use App\Actions\Crm\MoveOpportunity;
use App\Actions\Finance\CreateFinancialAccount;
use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\GenerateRecurringExpenses;
use App\Actions\Finance\RecordManualTransaction;
use App\Actions\Finance\RecordPayment;
use App\Actions\Proposals\ChangeProposalStatus;
use App\Actions\Proposals\ConvertProposalToContract;
use App\Actions\Proposals\SaveProposal;
use App\Actions\ServiceOrders\ChangeServiceOrderStatus;
use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Actions\Tickets\AddTicketMessage;
use App\Actions\Tickets\ChangeTicketStatus;
use App\Actions\Tickets\SaveTicket;
use App\Enums\BillingFrequency;
use App\Enums\BillStatus;
use App\Enums\CatalogItemType;
use App\Enums\ContractStatus;
use App\Enums\CustomerStatus;
use App\Enums\FinancialAccountType;
use App\Enums\FinancialCategoryType;
use App\Enums\LeadStatus;
use App\Enums\PersonType;
use App\Enums\Priority;
use App\Enums\ProposalStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyUnit;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\Interaction;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Opportunity;
use App\Models\Payable;
use App\Models\PipelineStage;
use App\Models\Receivable;
use App\Models\RecurringExpense;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TicketCategory;
use App\Models\User;
use App\Support\BrazilianDocument;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Dados demonstrativos realistas e INTERLIGADOS.
 *
 * Em vez de inserir registros "soltos", o seeder usa as mesmas Actions da
 * aplicação (SaveProposal, ConvertProposalToContract, RecordPayment...):
 * os dados resultantes respeitam todas as regras de negócio, e o extrato
 * das contas financeiras confere com os saldos.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Senha de TODOS os usuários de demonstração (somente desenvolvimento local).
     */
    public const string DEMO_PASSWORD = 'Nexus@2026';

    private CarbonImmutable $today;

    public function run(): void
    {
        fake()->seed(2026);
        $this->today = CarbonImmutable::today();

        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Administrador da Plataforma',
            'email' => 'admin@nexuserp.local',
            'password' => self::DEMO_PASSWORD,
            'job_title' => 'Superadministrador',
        ]);

        Auth::setUser($superAdmin);

        $main = app(CreateCompany::class)->handle([
            'legal_name' => 'Vértice Soluções em Tecnologia Ltda',
            'trade_name' => 'Vértice TI',
            'cnpj' => BrazilianDocument::generateCnpj(),
            'email' => 'contato@verticeti.local',
            'phone' => '1133445566',
            'zip_code' => '01310100',
            'street' => 'Avenida Paulista',
            'number' => '1500',
            'complement' => 'Conjunto 82',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $secondary = app(CreateCompany::class)->handle([
            'legal_name' => 'Órbita Facilities e Manutenção Ltda',
            'trade_name' => 'Órbita Facilities',
            'cnpj' => BrazilianDocument::generateCnpj(),
            'email' => 'contato@orbitafacilities.local',
            'phone' => '1932221100',
            'street' => 'Rua Barão de Jaguara',
            'number' => '900',
            'district' => 'Centro',
            'city' => 'Campinas',
            'state' => 'SP',
        ]);

        $mainUsers = $this->seedCompany($main, 'verticeti', primary: true);
        $this->seedCompany($secondary, 'orbita', primary: false, sharedUser: $mainUsers['manager']);

        $superAdmin->forceFill(['current_company_id' => $main->id])->saveQuietly();
    }

    /**
     * @return array<string, User>
     */
    private function seedCompany(Company $company, string $domain, bool $primary, ?User $sharedUser = null): array
    {
        return app(CurrentCompany::class)->run($company, function (Company $company) use ($domain, $primary, $sharedUser): array {
            $company->settingsOrDefault()->update([
                'default_payment_terms' => 'Faturamento mensal com vencimento no dia 10. Reajuste anual pelo IPCA.',
                'notification_email' => "financeiro@{$domain}.local",
            ]);

            $units = $this->seedUnits($company);
            $users = $this->seedUsers($company, $domain, $units, $primary);

            if ($sharedUser !== null) {
                // Usuário que trabalha nas duas empresas (demonstra o seletor de empresa).
                $this->attachUser($company, $sharedUser, 'manager');
            }

            $scale = $primary ? 1 : 0.35;

            $catalog = $this->seedCatalog($primary);
            $customers = $this->seedCustomers((int) round(32 * $scale), $users);
            $suppliers = $this->seedSuppliers((int) round(10 * $scale));
            $accounts = $this->seedAccounts($primary);

            $this->seedCrm($customers, $users, (int) round(16 * $scale));
            $contracts = $this->seedProposalsAndContracts($customers, $catalog, $users, (int) round(26 * $scale), $units);
            $this->seedFinance($contracts, $customers, $suppliers, $accounts, $users);
            $this->seedOperations($customers, $contracts, $catalog, $users, (int) round(22 * $scale));

            return $users;
        });
    }

    /**
     * @return Collection<int, CompanyUnit>
     */
    private function seedUnits(Company $company): Collection
    {
        return collect([
            ['name' => 'Matriz', 'code' => 'MTZ', 'city' => $company->city, 'state' => $company->state],
            ['name' => 'Filial Interior', 'code' => 'FIL', 'city' => 'Ribeirão Preto', 'state' => 'SP'],
        ])->map(fn (array $data) => CompanyUnit::create([...$data, 'is_active' => true, 'email' => null]));
    }

    /**
     * @param  Collection<int, CompanyUnit>  $units
     * @return array<string, User>
     */
    private function seedUsers(Company $company, string $domain, Collection $units, bool $primary): array
    {
        $people = [
            'company-admin' => ['Helena Martins', 'Diretora administrativa'],
            'manager' => ['Rafael Albuquerque', 'Gerente de operações'],
            'sales' => ['Camila Rocha', 'Executiva de contas'],
            'finance' => ['Bruno Teixeira', 'Analista financeiro'],
            'support' => ['Juliana Pires', 'Coordenadora de atendimento'],
            'collaborator' => ['Diego Fernandes', 'Técnico de campo'],
        ];

        if (! $primary) {
            $people = [
                'company-admin' => ['Marcos Vieira', 'Sócio-diretor'],
                'finance' => ['Patrícia Lima', 'Assistente financeira'],
                'collaborator' => ['Renato Souza', 'Técnico de manutenção'],
            ];
        }

        $users = [];

        foreach ($people as $roleSlug => [$name, $jobTitle]) {
            $emailPrefix = match ($roleSlug) {
                'company-admin' => 'admin',
                'manager' => 'gerente',
                'sales' => 'comercial',
                'finance' => 'financeiro',
                'support' => 'atendimento',
                default => 'colaborador',
            };

            $user = User::factory()->create([
                'name' => $name,
                'email' => "{$emailPrefix}@{$domain}.local",
                'password' => self::DEMO_PASSWORD,
                'job_title' => $jobTitle,
                'current_company_id' => $company->id,
            ]);

            $this->attachUser($company, $user, $roleSlug, $units->random()->id);
            $users[$roleSlug] = $user;
        }

        if ($primary) {
            // Mais técnicos e vendedores para dar volume às atribuições.
            foreach ([['Larissa Campos', 'sales', 'comercial2'], ['Thiago Moreira', 'collaborator', 'tecnico2'], ['Aline Duarte', 'support', 'atendimento2']] as [$name, $role, $prefix]) {
                $user = User::factory()->create(['name' => $name, 'email' => "{$prefix}@{$domain}.local", 'password' => self::DEMO_PASSWORD, 'current_company_id' => $company->id]);
                $this->attachUser($company, $user, $role, $units->random()->id);
                $users[$prefix] = $user;
            }
        }

        return $users;
    }

    private function attachUser(Company $company, User $user, string $roleSlug, ?int $unitId = null): void
    {
        CompanyMembership::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $roleSlug)->value('id'),
            'company_unit_id' => $unitId,
            'is_active' => true,
        ]);
    }

    /**
     * @return Collection<int, CatalogItem>
     */
    private function seedCatalog(bool $primary): Collection
    {
        $items = $primary ? [
            ['SRV-SUP-01', 'Suporte técnico mensal — Plano Essencial', 'Suporte', 'MÊS', 189000],
            ['SRV-SUP-02', 'Suporte técnico mensal — Plano Avançado', 'Suporte', 'MÊS', 349000],
            ['SRV-CON-01', 'Hora de consultoria em infraestrutura', 'Consultoria', 'H', 28000],
            ['SRV-CON-02', 'Diagnóstico de segurança da informação', 'Consultoria', 'UN', 650000],
            ['SRV-IMP-01', 'Implantação de servidor e rede', 'Implantação', 'UN', 480000],
            ['SRV-IMP-02', 'Migração para nuvem', 'Implantação', 'UN', 920000],
            ['SRV-TRE-01', 'Treinamento de usuários (turma)', 'Treinamento', 'UN', 180000],
            ['SRV-BKP-01', 'Backup gerenciado em nuvem', 'Suporte', 'MÊS', 59000],
            ['PRD-LIC-01', 'Licença antivírus corporativo (por estação)', 'Licenças', 'UN', 9900],
            ['PRD-LIC-02', 'Licença de firewall (anual)', 'Licenças', 'UN', 289000],
            ['PRD-EQP-01', 'Roteador corporativo', 'Equipamentos', 'UN', 159000],
        ] : [
            ['SRV-MAN-01', 'Manutenção predial preventiva mensal', 'Suporte', 'MÊS', 420000],
            ['SRV-LIM-01', 'Limpeza técnica de ambientes', 'Suporte', 'MÊS', 260000],
            ['SRV-VIS-01', 'Visita técnica avulsa', 'Consultoria', 'UN', 35000],
        ];

        return collect($items)->map(function (array $item) {
            [$code, $name, $categoryName, $unit, $price] = $item;
            $type = str_starts_with($code, 'PRD') ? CatalogItemType::Product : CatalogItemType::Service;

            return CatalogItem::create([
                'catalog_category_id' => CatalogCategory::query()->where('type', $type)->where('name', $categoryName)->value('id'),
                'type' => $type,
                'code' => $code,
                'name' => $name,
                'description' => fake()->sentence(12),
                'unit' => $unit,
                'price_cents' => $price,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array<string, User>  $users
     * @return Collection<int, Customer>
     */
    private function seedCustomers(int $count, array $users): Collection
    {
        $owners = collect($users)->filter(fn (User $user, string $key) => in_array($key, ['sales', 'comercial2', 'company-admin', 'manager'], true))->values();
        $segments = ['Clínica', 'Escritório de Advocacia', 'Indústria', 'Construtora', 'Escola', 'Transportadora', 'Contabilidade', 'Varejo', 'Laboratório', 'Agência'];

        return collect(range(1, max(1, $count)))->map(function (int $index) use ($owners, $segments) {
            $isCompany = $index % 5 !== 0;
            $tradeName = $isCompany ? fake()->randomElement($segments).' '.fake()->lastName() : null;

            $customer = Customer::create([
                'type' => $isCompany ? PersonType::Company : PersonType::Individual,
                'name' => $isCompany ? $tradeName.' '.fake()->randomElement(['Ltda', 'S.A.', 'ME', 'EIRELI']) : fake()->name(),
                'trade_name' => $tradeName,
                'document' => $isCompany ? BrazilianDocument::generateCnpj() : BrazilianDocument::generateCpf(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->numerify('11 3###-####'),
                'mobile' => fake()->numerify('11 9####-####'),
                'status' => $index % 11 === 0 ? CustomerStatus::Inactive : CustomerStatus::Active,
                'owner_id' => $owners->random()->id,
                'notes' => $index % 3 === 0 ? 'Prefere contato por WhatsApp no período da tarde.' : null,
            ]);

            // Distribui as datas de cadastro nos últimos 8 meses (gráfico de novos clientes).
            $createdAt = $this->today->subDays(fake()->numberBetween(5, 240))->setTime(10, 0);
            $customer->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

            $customer->addresses()->create([
                'label' => 'Principal',
                'zip_code' => fake()->numerify('0#######'),
                'street' => fake()->streetName(),
                'number' => (string) fake()->buildingNumber(),
                'district' => fake()->randomElement(['Centro', 'Jardins', 'Moema', 'Pinheiros', 'Vila Mariana']),
                'city' => fake()->randomElement(['São Paulo', 'Campinas', 'Santos', 'Guarulhos', 'Osasco']),
                'state' => 'SP',
                'is_primary' => true,
            ]);

            if ($isCompany) {
                $customer->contacts()->create([
                    'name' => fake()->name(),
                    'position' => fake()->randomElement(['Diretor(a)', 'Gerente administrativo', 'Coordenador(a) de TI', 'Sócio(a)']),
                    'email' => fake()->unique()->safeEmail(),
                    'phone' => fake()->numerify('11 9####-####'),
                    'is_primary' => true,
                ]);
            }

            return $customer;
        });
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function seedSuppliers(int $count): Collection
    {
        $names = ['Imobiliária Central', 'Telecom Brasil Fibra', 'Contábil Exata', 'Cloud Host Datacenter', 'Distribuidora InfoTec', 'Papelaria Comercial', 'Seguros Proteção', 'Limpeza Brilho', 'Software Gestão Plus', 'Energia Elétrica SP'];

        return collect(array_slice($names, 0, max(3, $count)))->map(fn (string $name) => Supplier::create([
            'type' => PersonType::Company,
            'name' => $name.' Ltda',
            'trade_name' => $name,
            'document' => BrazilianDocument::generateCnpj(),
            'email' => Str::slug($name).'@fornecedor.local',
            'phone' => fake()->numerify('11 3###-####'),
            'status' => 'active',
        ]));
    }

    /**
     * @return Collection<int, FinancialAccount>
     */
    private function seedAccounts(bool $primary): Collection
    {
        $openingDate = $this->today->subMonths(9)->startOfMonth()->toDateString();
        $create = app(CreateFinancialAccount::class);

        $accounts = [
            ['name' => 'Itaú — Conta PJ', 'type' => FinancialAccountType::Checking, 'bank_name' => 'Itaú Unibanco', 'bank_code' => '341', 'agency' => '0912', 'account_number' => '45120-3', 'opening_balance_cents' => $primary ? 8_500_000 : 2_000_000],
            ['name' => 'Banco Digital — Conta PJ', 'type' => FinancialAccountType::DigitalWallet, 'bank_name' => 'Banco Digital', 'bank_code' => '260', 'agency' => '0001', 'account_number' => '9988776-1', 'opening_balance_cents' => $primary ? 2_200_000 : 500_000],
            ['name' => 'Caixa interno', 'type' => FinancialAccountType::Cash, 'opening_balance_cents' => 150_000],
        ];

        return collect($accounts)->map(fn (array $data) => $create->handle([...$data, 'opening_date' => $openingDate, 'is_active' => true]));
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  array<string, User>  $users
     */
    private function seedCrm(Collection $customers, array $users, int $leadCount): void
    {
        $sources = LeadSource::query()->pluck('id');
        $stages = PipelineStage::query()->ordered()->get();
        $openStages = $stages->reject->isClosing()->values();
        $sellers = collect([$users['sales'] ?? null, $users['comercial2'] ?? null, $users['company-admin']])->filter()->values();
        $move = app(MoveOpportunity::class);

        $leads = collect(range(1, max(2, $leadCount)))->map(function (int $index) use ($sources, $sellers) {
            $status = fake()->randomElement([LeadStatus::New, LeadStatus::New, LeadStatus::Contacted, LeadStatus::Qualified, LeadStatus::Disqualified]);

            $lead = Lead::create([
                'name' => fake()->name(),
                'company_name' => fake()->company(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->numerify('11 9####-####'),
                'lead_source_id' => $sources->random(),
                'status' => $status,
                'estimated_value_cents' => fake()->numberBetween(30, 400) * 10000,
                'owner_id' => $sellers->random()->id,
                'next_contact_at' => $status === LeadStatus::Disqualified ? null : $this->today->addDays(fake()->numberBetween(-3, 15)),
                'notes' => 'Interesse em '.fake()->randomElement(['suporte mensal', 'migração para nuvem', 'segurança da informação', 'implantação de rede']).'.',
            ]);

            Interaction::factory()->create([
                'interactable_type' => 'lead', 'interactable_id' => $lead->id, 'user_id' => $lead->owner_id,
            ]);

            return $lead;
        });

        // Oportunidades em várias etapas (persistidas na ordem do Kanban).
        foreach (range(1, max(3, (int) round($leadCount * 1.3))) as $index) {
            $fromLead = $index % 3 === 0;
            $opportunity = Opportunity::create([
                'title' => fake()->randomElement(['Contrato de suporte', 'Projeto de migração', 'Renovação de licenças', 'Implantação de rede', 'Consultoria de segurança']).' — '.fake()->lastName(),
                'customer_id' => $fromLead ? null : $customers->random()->id,
                'lead_id' => $fromLead ? $leads->random()->id : null,
                'pipeline_stage_id' => $openStages->first()->id,
                'owner_id' => $sellers->random()->id,
                'estimated_value_cents' => fake()->numberBetween(20, 600) * 10000,
                'probability' => $openStages->first()->probability,
                'expected_close_date' => $this->today->addDays(fake()->numberBetween(5, 90)),
                'status' => 'open',
                'description' => fake()->sentence(),
            ]);

            $target = $index % 9 === 0 ? $stages->firstWhere('is_lost', true) : ($index % 7 === 0 ? $stages->firstWhere('is_won', true) : $openStages->random());
            $move->handle($opportunity, $target, null, $target->is_lost ? 'Preço acima do orçamento do cliente' : null);

            Task::factory()->create([
                'taskable_type' => 'opportunity', 'taskable_id' => $opportunity->id,
                'assigned_to' => $opportunity->owner_id, 'created_by' => $opportunity->owner_id,
            ]);
        }

        // Interações e tarefas no histórico dos clientes.
        $customers->random(min(12, $customers->count()))->each(function (Customer $customer) {
            Interaction::factory()->count(fake()->numberBetween(1, 3))->create([
                'interactable_type' => 'customer', 'interactable_id' => $customer->id, 'user_id' => $customer->owner_id,
            ]);
        });

        $customers->random(min(6, $customers->count()))->each(fn (Customer $customer) => Task::factory()->create([
            'taskable_type' => 'customer', 'taskable_id' => $customer->id,
            'assigned_to' => $customer->owner_id, 'created_by' => $customer->owner_id,
        ]));
    }

    /**
     * Propostas em todos os status; as aprovadas viram contratos ativos.
     *
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, CatalogItem>  $catalog
     * @param  array<string, User>  $users
     * @param  Collection<int, CompanyUnit>  $units
     * @return Collection<int, Contract>
     */
    private function seedProposalsAndContracts(Collection $customers, Collection $catalog, array $users, int $count, Collection $units): Collection
    {
        $saveProposal = app(SaveProposal::class);
        $changeStatus = app(ChangeProposalStatus::class);
        $convert = app(ConvertProposalToContract::class);
        $changeContract = app(ChangeContractStatus::class);
        $recurringCategory = FinancialCategory::query()->where('type', FinancialCategoryType::Income)->where('name', 'Serviços recorrentes')->value('id');
        $monthlyItems = $catalog->where('unit', 'MÊS')->values();
        $otherItems = $catalog->where('unit', '!=', 'MÊS')->values();
        $activeCustomers = $customers->where('status', CustomerStatus::Active)->values();
        $contracts = collect();

        foreach (range(1, max(3, $count)) as $index) {
            $seller = collect([$users['sales'] ?? null, $users['comercial2'] ?? null, $users['company-admin']])->filter()->random();
            Auth::setUser($seller);

            $customer = $activeCustomers->random();
            $main = $monthlyItems->random();
            $items = [[
                'catalog_item_id' => $main->id,
                'description' => $main->name,
                'quantity' => '1',
                'unit' => $main->unit,
                'unit_price_cents' => $main->price_cents,
                'discount_cents' => $index % 4 === 0 ? intdiv($main->price_cents, 10) : 0,
            ]];

            if ($otherItems->isNotEmpty() && $index % 2 === 0) {
                $extra = $otherItems->random();
                $items[] = [
                    'catalog_item_id' => $extra->id,
                    'description' => $extra->name,
                    'quantity' => $extra->unit === 'H' ? (string) fake()->numberBetween(4, 20) : '1',
                    'unit' => $extra->unit,
                    'unit_price_cents' => $extra->price_cents,
                    'discount_cents' => 0,
                ];
            }

            $issueDate = $this->today->subDays(fake()->numberBetween(3, 200));
            $proposal = $saveProposal->create([
                'customer_id' => $customer->id,
                'owner_id' => $seller->id,
                'title' => $main->name.' — '.$customer->display_name,
                'issue_date' => $issueDate->toDateString(),
                'valid_until' => $issueDate->addDays(20)->toDateString(),
                'discount_cents' => 0,
                'items' => $items,
                'notes' => 'Proposta válida mediante aceite formal do cliente.',
            ]);

            // Destino de cada proposta: distribuição realista entre os status.
            $destiny = match (true) {
                $index % 10 === 0 => ProposalStatus::Rejected,
                $index % 9 === 0 => ProposalStatus::Cancelled,
                $index % 6 === 0 => ProposalStatus::Draft,
                $index % 5 === 0 => ProposalStatus::Sent,
                default => ProposalStatus::Approved,
            };

            if ($destiny === ProposalStatus::Draft) {
                continue;
            }

            if ($destiny === ProposalStatus::Cancelled) {
                $changeStatus->handle($proposal, ProposalStatus::Cancelled, 'Cliente desistiu do projeto.');

                continue;
            }

            // Para aprovar uma proposta antiga, a validade precisa estar vigente no momento da aprovação.
            if ($destiny === ProposalStatus::Approved && $proposal->isPastValidity()) {
                $proposal->forceFill(['valid_until' => $this->today->addDays(10)->toDateString()])->saveQuietly();
            }

            $changeStatus->handle($proposal, ProposalStatus::Sent);

            if ($destiny === ProposalStatus::Sent) {
                continue;
            }

            if ($destiny === ProposalStatus::Rejected) {
                $changeStatus->handle($proposal, ProposalStatus::Rejected, 'Optou por um fornecedor com menor preço.');

                continue;
            }

            Auth::setUser($users['company-admin']);
            $changeStatus->handle($proposal, ProposalStatus::Approved);

            $start = $issueDate->addDays(fake()->numberBetween(3, 10));
            $contract = $convert->handle($proposal->fresh(), [
                'start_date' => $start->toDateString(),
                'end_date' => $index % 3 === 0 ? $start->addMonths(12)->toDateString() : null,
                'billing_frequency' => $index % 8 === 0 ? BillingFrequency::Quarterly->value : BillingFrequency::Monthly->value,
                'billing_day' => fake()->randomElement([5, 10, 15, 20]),
                'auto_renew' => $index % 3 === 0,
                'financial_category_id' => $recurringCategory,
            ]);
            $contract->forceFill(['company_unit_id' => $units->random()->id])->saveQuietly();

            $changeContract->handle($contract, ContractStatus::Active, 'Contrato assinado pelo cliente.');

            if ($index % 13 === 0) {
                $changeContract->handle($contract, ContractStatus::Suspended, 'Suspensão temporária a pedido do cliente.');
            }

            // Datas retroativas para o histórico parecer real (conversão no período, etc.).
            $proposal->forceFill([
                'sent_at' => $issueDate->addDay(),
                'approved_at' => $start->subDay(),
                'updated_at' => $start->subDay(),
            ])->saveQuietly();

            $contracts->push($contract->fresh());
        }

        Auth::setUser($users['company-admin']);

        return $contracts;
    }

    /**
     * Gera o histórico financeiro: cobranças dos contratos (desde o início),
     * despesas recorrentes, títulos avulsos, pagamentos, transferências e tarifas.
     *
     * @param  Collection<int, Contract>  $contracts
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Supplier>  $suppliers
     * @param  Collection<int, FinancialAccount>  $accounts
     * @param  array<string, User>  $users
     */
    private function seedFinance(Collection $contracts, Collection $customers, Collection $suppliers, Collection $accounts, array $users): void
    {
        Auth::setUser($users['finance'] ?? $users['company-admin']);

        $charges = app(GenerateContractCharges::class);
        $horizon = $this->today->addDays(10);

        foreach ($contracts as $contract) {
            if ($contract->status->generatesCharges()) {
                $charges->forContract($contract, $horizon);
            }
        }

        $incomeCategories = FinancialCategory::query()->where('type', FinancialCategoryType::Income)->pluck('id', 'name');
        $expenseCategories = FinancialCategory::query()->where('type', FinancialCategoryType::Expense)->pluck('id', 'name');
        $costCenters = CostCenter::query()->pluck('id', 'code');

        // Recebíveis avulsos (projetos pontuais).
        foreach ($customers->random(min(8, $customers->count())) as $customer) {
            $due = $this->today->subDays(fake()->numberBetween(-25, 150));
            Receivable::create([
                'customer_id' => $customer->id,
                'financial_category_id' => $incomeCategories['Projetos e implantação'] ?? null,
                'description' => 'Projeto pontual — '.fake()->randomElement(['instalação de rede', 'treinamento', 'consultoria']),
                'document_number' => 'NF '.fake()->numberBetween(1000, 9999),
                'amount_cents' => fake()->numberBetween(15, 120) * 10000,
                'issue_date' => $due->subDays(15)->toDateString(),
                'due_date' => $due->toDateString(),
                'competence' => $due->startOfMonth()->toDateString(),
            ]);
        }

        // Despesas recorrentes desde 6 meses atrás (geradas pela própria rotina).
        $recurring = [
            ['Aluguel do escritório', 'Imobiliária Central', 'Aluguel e condomínio', 'ADM', 980000, 5],
            ['Internet dedicada', 'Telecom Brasil Fibra', 'Softwares e assinaturas', 'TI', 89000, 12],
            ['Honorários contábeis', 'Contábil Exata', 'Fornecedores de serviços', 'ADM', 165000, 8],
            ['Hospedagem e servidores em nuvem', 'Cloud Host Datacenter', 'Softwares e assinaturas', 'TI', 245000, 15],
            ['Sistema de gestão (assinatura)', 'Software Gestão Plus', 'Softwares e assinaturas', 'TI', 39900, 20],
        ];

        // A empresa menor tem só as três primeiras despesas fixas.
        $recurring = $contracts->count() > 8 ? $recurring : array_slice($recurring, 0, 3);

        foreach ($recurring as [$description, $supplierName, $categoryName, $costCenter, $amount, $dueDay]) {
            RecurringExpense::create([
                'supplier_id' => $suppliers->firstWhere('trade_name', $supplierName)?->id ?? $suppliers->first()->id,
                'financial_category_id' => $expenseCategories[$categoryName] ?? null,
                'cost_center_id' => $costCenters[$costCenter] ?? null,
                'description' => $description,
                'amount_cents' => $amount,
                'frequency' => BillingFrequency::Monthly,
                'due_day' => $dueDay,
                'start_date' => $this->today->subMonths(6)->startOfMonth()->toDateString(),
                'next_due_date' => $this->today->subMonths(6)->startOfMonth()->setDay($dueDay)->toDateString(),
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);
        }

        app(GenerateRecurringExpenses::class)->forCurrentCompany(10, $this->today);

        // Despesas avulsas.
        foreach (range(1, 8) as $index) {
            $due = $this->today->subDays(fake()->numberBetween(-20, 160));
            Payable::create([
                'supplier_id' => $suppliers->random()->id,
                'financial_category_id' => $expenseCategories->random(),
                'cost_center_id' => $costCenters->random(),
                'description' => fake()->randomElement(['Compra de equipamentos', 'Campanha de marketing digital', 'Manutenção de veículos', 'Material de escritório', 'Licenças de software']),
                'document_number' => 'NF '.fake()->numberBetween(10000, 99999),
                'amount_cents' => fake()->numberBetween(3, 60) * 10000,
                'issue_date' => $due->subDays(10)->toDateString(),
                'due_date' => $due->toDateString(),
                'competence' => $due->startOfMonth()->toDateString(),
                'created_by' => Auth::id(),
            ]);
        }

        $this->settleHistory($accounts);

        // Transferência entre contas (limitada ao saldo disponível da origem).
        $available = (int) FinancialAccount::query()->whereKey($accounts[0]->id)->value('current_balance_cents');

        if ($available > 100_000) {
            app(CreateTransfer::class)->handle([
                'from_account_id' => $accounts[0]->id,
                'to_account_id' => $accounts[1]->id,
                'amount_cents' => min(500_000, intdiv($available, 2)),
                'transferred_on' => $this->today->subDays(20)->toDateString(),
                'description' => 'Reserva para impostos',
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }

        // Tarifas bancárias mensais (lançamentos avulsos), lançadas na conta com maior saldo.
        $manual = app(RecordManualTransaction::class);
        $feeAccount = FinancialAccount::query()->whereIn('id', $accounts->pluck('id'))->orderByDesc('current_balance_cents')->firstOrFail();

        foreach (range(1, 6) as $monthsAgo) {
            $manual->handle($feeAccount, [
                'direction' => 'out',
                'amount_cents' => 8_990,
                'occurred_on' => $this->today->subMonths($monthsAgo)->endOfMonth()->toDateString(),
                'description' => 'Tarifa de manutenção de conta',
                'financial_category_id' => $expenseCategories['Tarifas bancárias'] ?? null,
            ]);
        }
    }

    /**
     * Registra pagamentos para os títulos já vencidos, de forma realista:
     * a maioria é paga perto do vencimento; alguns ficam parciais ou em atraso.
     *
     * @param  Collection<int, FinancialAccount>  $accounts
     */
    private function settleHistory(Collection $accounts): void
    {
        $recordPayment = app(RecordPayment::class);
        $mainAccount = $accounts[0];

        // Primeiro os recebimentos (entradas), depois os pagamentos (saídas).
        $receivables = Receivable::query()->where('status', BillStatus::Open)->whereDate('due_date', '<', $this->today->subDays(3))->orderBy('due_date')->get();

        foreach ($receivables as $index => $receivable) {
            $roll = $index % 10;

            if ($roll === 0) {
                continue; // Inadimplente: permanece vencido.
            }

            $amount = $roll === 1 ? intdiv($receivable->amount_cents, 2) : $receivable->amount_cents;
            $paidOn = CarbonImmutable::parse($receivable->due_date)->addDays(fake()->numberBetween(-2, 4));

            $recordPayment->handle($receivable, [
                'financial_account_id' => ($index % 4 === 0 ? $accounts[1] : $mainAccount)->id,
                'amount_cents' => $amount,
                'paid_on' => min($paidOn, $this->today)->toDateString(),
                'method' => fake()->randomElement(['pix', 'bank_slip', 'bank_transfer']),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }

        $payables = Payable::query()->where('status', BillStatus::Open)->whereDate('due_date', '<', $this->today->subDays(2))->orderBy('due_date')->get();

        foreach ($payables as $index => $payable) {
            if ($index % 12 === 5) {
                continue; // Algumas contas a pagar em atraso.
            }

            try {
                $recordPayment->handle($payable, [
                    'financial_account_id' => $mainAccount->id,
                    'amount_cents' => $payable->amount_cents,
                    'paid_on' => CarbonImmutable::parse($payable->due_date)->toDateString(),
                    'method' => fake()->randomElement(['pix', 'bank_slip']),
                    'idempotency_key' => (string) Str::uuid(),
                ]);
            } catch (BusinessRuleException) {
                // Saldo insuficiente: a conta fica em atraso, como aconteceria na vida real.
            }
        }

    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Contract>  $contracts
     * @param  Collection<int, CatalogItem>  $catalog
     * @param  array<string, User>  $users
     */
    private function seedOperations(Collection $customers, Collection $contracts, Collection $catalog, array $users, int $count): void
    {
        $saveOrder = app(SaveServiceOrder::class);
        $changeOrder = app(ChangeServiceOrderStatus::class);
        $saveTicket = app(SaveTicket::class);
        $changeTicket = app(ChangeTicketStatus::class);
        $addMessage = app(AddTicketMessage::class);

        $technicians = collect($users)->filter(fn (User $user, string $key) => in_array($key, ['collaborator', 'tecnico2', 'support', 'manager'], true))->values();
        $agents = collect($users)->filter(fn (User $user, string $key) => in_array($key, ['support', 'atendimento2', 'company-admin'], true))->values();
        $services = $catalog->where('type', CatalogItemType::Service)->values();
        $categories = TicketCategory::query()->pluck('id');

        Auth::setUser($users['support'] ?? $users['company-admin']);

        foreach (range(1, max(3, $count)) as $index) {
            $contract = $contracts->isNotEmpty() && $index % 2 === 0 ? $contracts->random() : null;
            $customerId = $contract?->customer_id ?? $customers->random()->id;

            $order = $saveOrder->create([
                'customer_id' => $customerId,
                'contract_id' => $contract?->id,
                'catalog_item_id' => $services->random()->id,
                'title' => fake()->randomElement(['Manutenção preventiva', 'Instalação de estação de trabalho', 'Configuração de VPN', 'Troca de switch', 'Visita técnica', 'Atualização de servidor']),
                'description' => fake()->paragraph(),
                'priority' => fake()->randomElement(Priority::cases()),
                'assigned_to' => $technicians->random()->id,
                'due_date' => $this->today->addDays(fake()->numberBetween(-10, 20))->toDateString(),
            ]);

            $order->activities()->create(['user_id' => $order->assigned_to, 'description' => 'Contato inicial com o cliente para agendamento.', 'minutes_spent' => 15]);

            match ($index % 5) {
                0 => null,
                1, 2 => $changeOrder->handle($order, ServiceOrderStatus::InProgress),
                3 => $this->completeOrder($changeOrder, $order),
                4 => $changeOrder->handle($order, ServiceOrderStatus::OnHold, 'Aguardando peça do fornecedor.'),
            };
        }

        foreach (range(1, max(3, $count)) as $index) {
            $contract = $contracts->isNotEmpty() && $index % 3 === 0 ? $contracts->random() : null;

            $ticket = $saveTicket->create([
                'customer_id' => $contract?->customer_id ?? $customers->random()->id,
                'contract_id' => $contract?->id,
                'ticket_category_id' => $categories->random(),
                'subject' => fake()->randomElement(['Lentidão na rede', 'Impressora não funciona', 'Dúvida sobre a fatura', 'Solicitação de novo usuário', 'E-mail não sincroniza', 'Acesso remoto indisponível']),
                'description' => fake()->paragraph(),
                'priority' => fake()->randomElement(Priority::cases()),
                'channel' => fake()->randomElement(TicketChannel::cases()),
                'assigned_to' => $agents->random()->id,
            ]);

            $createdAt = $this->today->subDays(fake()->numberBetween(0, 60))->setTime(fake()->numberBetween(8, 17), 0);
            $ticket->forceFill(['created_at' => $createdAt])->saveQuietly();

            if ($index % 4 !== 0) {
                Auth::setUser($agents->random());
                $addMessage->handle($ticket, 'Olá! Recebemos sua solicitação e já estamos analisando.');
                $addMessage->handle($ticket, 'Cliente com contrato ativo — priorizar.', isInternal: true);
            }

            if ($index % 3 === 0) {
                $changeTicket->handle($ticket->fresh(), TicketStatus::Resolved, 'Problema resolvido remotamente.');
            }

            if ($index % 6 === 0) {
                $changeTicket->handle($ticket->fresh(), TicketStatus::Closed);
            }
        }
    }

    private function completeOrder(ChangeServiceOrderStatus $changeOrder, ServiceOrder $order): void
    {
        $changeOrder->handle($order, ServiceOrderStatus::InProgress);
        $order->activities()->create(['user_id' => $order->assigned_to, 'description' => 'Execução do serviço no local.', 'minutes_spent' => 120]);
        $changeOrder->handle($order, ServiceOrderStatus::Completed, 'Serviço executado e validado com o cliente.');
    }
}
