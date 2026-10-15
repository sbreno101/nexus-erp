# 08 — Testes automatizados

O Nexus ERP tem uma suíte de testes escrita com [Pest](https://pestphp.com/docs) (versão 5) sobre o PHPUnit, rodando contra um **PostgreSQL real** e isolado. Este documento explica como rodar, como a suíte está organizada, como ler uma falha e como escrever testes novos seguindo os padrões do projeto.

## Sumário

1. [Por que testar (e o que testar) neste projeto](#1-por-que-testar-e-o-que-testar-neste-projeto)
2. [Como rodar os testes](#2-como-rodar-os-testes)
3. [O banco de testes isolado](#3-o-banco-de-testes-isolado)
4. [RefreshDatabase e o seed de permissões](#4-refreshdatabase-e-o-seed-de-permissões)
5. [Helpers do `tests/Pest.php`](#5-helpers-do-testspestphp)
6. [Organização das pastas e o que cada arquivo cobre](#6-organização-das-pastas-e-o-que-cada-arquivo-cobre)
7. [Como interpretar falhas](#7-como-interpretar-falhas)
8. [Exemplos comentados](#8-exemplos-comentados)
9. [Escrevendo um teste novo, passo a passo](#9-escrevendo-um-teste-novo-passo-a-passo)
10. [Boas práticas adotadas](#10-boas-práticas-adotadas)
11. [Referências](#11-referências)

---

## 1. Por que testar (e o que testar) neste projeto

Um ERP mexe com dinheiro, contratos e dados de várias empresas. Os erros mais caros não aparecem clicando na tela: um pagamento registrado duas vezes por um duplo clique, uma cobrança recorrente gerada em duplicidade, um usuário da empresa A vendo um cliente da empresa B. A suíte foi construída para provar justamente essas garantias:

- **Regras de negócio** (Actions): pagamento parcial, estorno, idempotência, máquina de estados das propostas e contratos, cobrança recorrente.
- **Segurança**: permissões por perfil (403) e isolamento multiempresa (404), sempre testados **no backend**.
- **Integração**: as telas abrem (teste de fumaça), a API responde no formato documentado, os componentes Livewire filtram e agem corretamente.

---

## 2. Como rodar os testes

Pré-requisito: a infraestrutura Docker no ar (`docker compose up -d`), porque os testes usam o PostgreSQL.

```bash
# Suíte completa (saída compacta)
php artisan test --compact

# Um arquivo
php artisan test --compact tests/Feature/Finance/PaymentTest.php

# Uma pasta
php artisan test --compact tests/Feature/Finance

# Filtrar pelo nome do teste (trecho da descrição do it(...))
php artisan test --compact --filter="idempotente"

# Só uma testsuite (definidas no phpunit.xml)
php artisan test --compact --testsuite=Unit

# Chamando o Pest diretamente (aceita os mesmos argumentos)
vendor/bin/pest tests/Unit/MoneyTest.php

# Script do Composer: limpa o cache de configuração e roda a suíte
composer test
```

> **Dica importante:** se você rodou `php artisan config:cache` em algum momento, a configuração fica congelada e as variáveis do `phpunit.xml` (como `DB_DATABASE=nexus_erp_testing`) são **ignoradas** — os testes passariam a usar o banco de desenvolvimento! O script `composer test` executa `config:clear` antes justamente por isso. Em caso de dúvida, rode `php artisan config:clear`.

Na data desta documentação, a suíte tem **169 testes e 605 asserções** (cada linha de *dataset* conta como um teste) e leva cerca de **2 minutos** — o teste de fumaça, que carrega todos os dados demonstrativos, é o mais lento. O número cresce conforme o projeto evolui.

Há também scripts do Composer que combinam ferramentas de qualidade:

```bash
composer lint      # Pint (formatação)
composer analyse   # Larastan/PHPStan (análise estática, nível 1 — veja phpstan.neon)
composer check     # lint + analyse + test: rode antes de cada commit
```

---

## 3. O banco de testes isolado

Os testes **nunca** usam o banco de desenvolvimento (`nexus_erp`). Isso é garantido em dois lugares:

**1. O [`phpunit.xml`](../phpunit.xml) sobrescreve variáveis de ambiente durante os testes:**

```xml
<env name="APP_ENV" value="testing"/>
<env name="BCRYPT_ROUNDS" value="4"/>
<env name="CACHE_STORE" value="array"/>
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="nexus_erp_testing"/>
<env name="DB_URL" value=""/>
<env name="MAIL_MAILER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
```

| Variável | Valor | Por quê |
|---|---|---|
| `DB_DATABASE` | `nexus_erp_testing` | Banco separado: os testes apagam e recriam tabelas à vontade. Host, porta, usuário e senha continuam vindo do `.env` (porta 5433). |
| `BCRYPT_ROUNDS` | `4` | Hash de senha mais barato: acelera a criação de usuários nas factories. |
| `CACHE_STORE` / `SESSION_DRIVER` | `array` | Tudo em memória, sem depender do Redis nem deixar lixo entre testes. |
| `QUEUE_CONNECTION` | `sync` | Jobs e notificações enfileiradas rodam na hora, dentro do teste. |
| `MAIL_MAILER` | `array` | Nenhum e-mail sai de verdade. |

> Por que PostgreSQL e não SQLite em memória (o padrão de muitos projetos Laravel)? Porque o projeto depende de recursos específicos do PostgreSQL — índices UNIQUE **parciais** (`WHERE deleted_at IS NULL`), `CHECK constraints`, `ilike`, `lockForUpdate`. Testar em outro banco esconderia exatamente os bugs que esses recursos previnem. Por exemplo, o teste "o banco rejeita chaves de idempotência duplicadas mesmo sem passar pela Action" só faz sentido com o índice real.

**2. O container do PostgreSQL cria o banco de testes automaticamente** na primeira inicialização, pelo script [`docker/pgsql/create-testing-database.sql`](../docker/pgsql/create-testing-database.sql), montado em `docker-entrypoint-initdb.d` pelo [`docker-compose.yml`](../docker-compose.yml):

```sql
SELECT 'CREATE DATABASE nexus_erp_testing OWNER ' || current_user
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'nexus_erp_testing')\gexec
```

> Scripts de `docker-entrypoint-initdb.d` rodam **somente quando o volume de dados está vazio** (primeira subida). Se o seu volume `nexus-pgsql` foi criado antes desse script existir e os testes falharem com `database "nexus_erp_testing" does not exist`, crie o banco manualmente:
>
> ```bash
> docker compose exec pgsql createdb -U nexus nexus_erp_testing
> ```

---

## 4. RefreshDatabase e o seed de permissões

O [`tests/Pest.php`](../tests/Pest.php) aplica o mesmo `TestCase` e o trait `RefreshDatabase` a todos os testes das pastas `Feature` e `Unit`:

```php
// tests/Pest.php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
```

Como o `RefreshDatabase` funciona:

1. Na **primeira** vez que um teste precisa do banco, ele executa `migrate:fresh` (apaga e recria todas as tabelas) no `nexus_erp_testing`.
2. Cada teste roda **dentro de uma transação** que é desfeita (*rollback*) ao final. Assim, o que um teste cria nunca aparece no próximo, e não é preciso recriar as tabelas a cada teste (o que seria muito lento).

O [`tests/TestCase.php`](../tests/TestCase.php) acrescenta o seed das permissões logo após a migração:

```php
// tests/TestCase.php
abstract class TestCase extends BaseTestCase
{
    /**
     * Após migrar o banco de testes, popula a tabela de permissões
     * (necessária para criar os perfis padrão das empresas).
     */
    protected bool $seed = true;

    protected string $seeder = PermissionSeeder::class;
}
```

Por que só as permissões? Porque elas são **dados de referência globais** (o catálogo de `config/permissions.php`): sem elas, `ProvisionCompanyDefaults` criaria perfis vazios e todo teste de permissão falharia. Já os dados de negócio (clientes, contratos...) cada teste cria para si, com factories — exceto o teste de fumaça, que carrega o `DemoDataSeeder` de propósito.

> Repare que os testes da pasta `Unit` também usam o `TestCase` do Laravel e o `RefreshDatabase`. Alguns deles (como `MoneyTest`) não tocam no banco, mas a configuração comum mantém tudo simples e permite usar helpers do Laravel (como o `Validator` no `BrazilianDocumentTest`).

---

## 5. Helpers do `tests/Pest.php`

Funções globais que deixam os testes curtos e legíveis. Todas estão em [`tests/Pest.php`](../tests/Pest.php).

| Helper | O que faz | Quando usar |
|---|---|---|
| `createCompany(array $attributes = [])` | Cria uma empresa pela factory **e** roda `ProvisionCompanyDefaults` (perfis, funil, categorias financeiras, centros de custo...). | Sempre que precisar de uma empresa "de verdade". |
| `createUserWithRole(Company $company, string $roleSlug = 'company-admin', array $attributes = [])` | Cria um usuário e o vincula à empresa com o perfil informado (`company-admin`, `manager`, `sales`, `finance`, `support`, `collaborator`). **Não** autentica. | Para criar outros usuários (responsáveis, técnicos, um segundo membro). |
| `actingAsRole(string $roleSlug = 'company-admin', ?Company $company = null)` | Cria empresa (se não informada) + usuário com o perfil, **autentica** (`actingAs`) e define a empresa ativa. Retorna o usuário. | Início da maioria dos testes de Feature. |
| `setCurrentCompany(Company $company)` | Define a empresa ativa no `CurrentCompany`. | Ao chamar Actions/factories fora de uma requisição HTTP. |
| `currentCompany()` | Retorna a empresa ativa (ou lança exceção se não houver). | Para recuperar a empresa criada pelo `actingAsRole()`. |

```php
// tests/Pest.php
function actingAsRole(string $roleSlug = 'company-admin', ?Company $company = null): User
{
    $company ??= createCompany();
    $user = createUserWithRole($company, $roleSlug);

    test()->actingAs($user);
    setCurrentCompany($company);

    return $user;
}
```

**Por que `setCurrentCompany()` é necessário?** Em uma requisição real, o middleware `SetCurrentCompany` define a empresa ativa. Mas quando um teste chama uma Action diretamente (`app(RecordPayment::class)->handle(...)`) ou usa uma factory (`Customer::factory()->create()`), não há requisição — e o trait `BelongsToCompany` precisa saber qual `company_id` preencher. As factories de models multiempresa usam o trait [`ResolvesCompany`](../database/factories/Concerns/ResolvesCompany.php), que pega a empresa ativa (ou cria uma nova, se não houver):

```php
// database/factories/Concerns/ResolvesCompany.php
protected function companyId(): Closure
{
    return fn (): int => app(CurrentCompany::class)->id() ?? Company::factory()->create()->id;
}
```

Para criar dados em **outra** empresa sem trocar o contexto do teste, use `CurrentCompany::run()`, como em `CompanyIsolationTest`:

```php
app(CurrentCompany::class)->run($this->companyB, function (): void {
    $this->customerB = Customer::factory()->create(['name' => 'Cliente Secreto da B']);
});
```

Algumas factories têm **estados** (states) prontos — use-os antes de montar atributos na mão:

| Factory | Estados |
|---|---|
| `UserFactory` | `unverified()`, `superAdmin()`, `inactive()` |
| `CustomerFactory` | `individual()`, `inactive()` |
| `CatalogItemFactory` | `product()` |
| `ProposalFactory` | `status(ProposalStatus $status)` |
| `ContractFactory` | `active(?string $nextBillingDate = null)` |
| `ReceivableFactory` | `overdue()`, `dueIn(int $days)` |
| `FinancialAccountFactory` | `withBalance(int $cents)` |

---

## 6. Organização das pastas e o que cada arquivo cobre

```text
tests/
├── Pest.php                 configuração do Pest e helpers globais
├── TestCase.php             TestCase base (seed de permissões)
├── Unit/                    lógica pura, sem HTTP
│   ├── MoneyTest.php
│   ├── BrazilianDocumentTest.php
│   └── LineItemCalculatorTest.php
└── Feature/                 regras de negócio, HTTP, API, Livewire
    ├── Api/ApiTest.php
    ├── Auth/AuthenticationTest.php
    ├── Authorization/RolePermissionTest.php
    ├── Commercial/{CrmTest, ProposalFlowTest, RecurringBillingTest}.php
    ├── Customers/CustomerCrudTest.php
    ├── Finance/{PaymentTest, TransferTest}.php
    ├── Livewire/LivewireComponentsTest.php
    ├── Operations/OperationsTest.php
    ├── Security/SecurityTest.php
    ├── Settings/UserManagementTest.php
    ├── Tenancy/CompanyIsolationTest.php
    └── SmokeTest.php
```

### Testes unitários (`tests/Unit`)

| Arquivo | Cobre |
|---|---|
| [`MoneyTest.php`](../tests/Unit/MoneyTest.php) | `Money::format` (padrão brasileiro, negativos), `Money::parse` (aceita "1.234,56", "R$ 1.234,56", "1234.56"; rejeita "1,234,5"), o clássico `0,10 + 0,20 = 0,30` e `Money::multiply` com arredondamento meio-para-cima via bcmath. |
| [`BrazilianDocumentTest.php`](../tests/Unit/BrazilianDocumentTest.php) | Validação de CPF e CNPJ (inclusive o **CNPJ alfanumérico** `12.ABC.345/01DE-35`), geradores para dados de desenvolvimento, máscara/limpeza e a regra de validação `CpfCnpj`. |
| [`LineItemCalculatorTest.php`](../tests/Unit/LineItemCalculatorTest.php) | Cálculo de itens de proposta/contrato: total por item, quantidade decimal ("1,5"), subtotal, desconto geral; rejeita desconto maior que o item/subtotal e quantidade zero. |

### Testes de funcionalidade (`tests/Feature`)

| Arquivo | Cobre |
|---|---|
| [`Api/ApiTest.php`](../tests/Feature/Api/ApiTest.php) | Emissão de token (201), credenciais inválidas (422), 401 em português sem token, listagem paginada e busca, criação de cliente reusando as validações da web, atualização sem apagar endereços omitidos, **fluxo comercial completo** (proposta → enviar → aprovar → converter em contrato ativo), pagamento **idempotente** (201 e depois 200), permissões do perfil (403), 404 em português, revogação de token. |
| [`Auth/AuthenticationTest.php`](../tests/Feature/Auth/AuthenticationTest.php) | Tela de login, login com sucesso (e `last_login_at`), credenciais inválidas, conta desativada, **rate limit** (429 após 5 tentativas), visitante redirecionado ao login, e-mail não verificado, desconexão de conta desativada durante a sessão, logout, usuário sem empresa ativa. |
| [`Authorization/RolePermissionTest.php`](../tests/Feature/Authorization/RolePermissionTest.php) | O que os perfis financeiro, comercial e colaborador podem e não podem fazer; mudança de permissão do perfil com efeito imediato; o perfil administrador não pode ser editado; só o superadministrador acessa a gestão de empresas. |
| [`Commercial/CrmTest.php`](../tests/Feature/Commercial/CrmTest.php) | Conversão de lead em cliente (preserva origem, atualiza oportunidades, não converte duas vezes) e movimentação de oportunidades no funil (posição, probabilidade, ganha/perdida/reaberta). |
| [`Commercial/ProposalFlowTest.php`](../tests/Feature/Commercial/ProposalFlowTest.php) | Numeração sequencial `PROP-AAAA-00001`, totais calculados no servidor, ciclo rascunho → enviada → aprovada com histórico, transições inválidas, motivo obrigatório para recusar, edição só em rascunho, conversão em contrato **uma única vez**, oportunidade marcada como ganha + notificação, expiração automática, fluxo completo até gerar cobrança. |
| [`Commercial/RecurringBillingTest.php`](../tests/Feature/Commercial/RecurringBillingTest.php) | Cobrança recorrente: janela de antecedência, idempotência (rodar duas vezes não duplica), índice UNIQUE `(contract_id, competence)`, contratos suspensos/rascunho/fora da janela, periodicidade trimestral, término do contrato, ativação, transição inválida, cancelamento cancelando cobranças futuras, reativação sem cobrar o período suspenso, renovação, despesas recorrentes. Usa `travelTo()` para fixar a data. |
| [`Customers/CustomerCrudTest.php`](../tests/Feature/Customers/CustomerCrudTest.php) | CRUD web de clientes: documento limpo, endereço e contato; CPF/CNPJ conforme o tipo; duplicidade na mesma empresa (mas permitida em outra); obrigatórios; atualização substituindo endereços; soft delete bloqueado com contrato ativo; exportação CSV respeitando a busca. |
| [`Finance/PaymentTest.php`](../tests/Feature/Finance/PaymentTest.php) | Baixa total e parcial, valor acima do saldo, **idempotência** na Action e no banco, estorno (e não estornar duas vezes), conta a pagar com saldo insuficiente (transação desfeita por completo), cancelamento com/sem pagamentos, pagamento em título cancelado, saldo da conta = extrato, **imutabilidade** do livro-razão. |
| [`Finance/TransferTest.php`](../tests/Feature/Finance/TransferTest.php) | Transferência entre contas com saldos consistentes, saldo insuficiente, mesma conta, idempotência, estorno, lançamentos avulsos e o comando `finance:verify-balances` detectando divergência. |
| [`Livewire/LivewireComponentsTest.php`](../tests/Feature/Livewire/LivewireComponentsTest.php) | Componentes Livewire com `Livewire::test()`: busca em tempo real de clientes, mover oportunidade no Kanban, perfil sem permissão (403), filtro de títulos vencidos, criar categoria financeira, dashboard consolidado ignorando empresas sem acesso. |
| [`Operations/OperationsTest.php`](../tests/Feature/Operations/OperationsTest.php) | Abertura de OS com numeração e notificação do responsável, contrato de outro cliente rejeitado, ciclo de status da OS (solução obrigatória para concluir), atividades com tempo gasto, chamados com primeira resposta pública, mensagens bloqueadas em chamado encerrado. |
| [`Security/SecurityTest.php`](../tests/Feature/Security/SecurityTest.php) | Cabeçalhos de segurança do middleware [`SecurityHeaders`](../app/Http/Middleware/SecurityHeaders.php) (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`), anexos gravados no **disco privado** com nome aleatório e download só com autorização, bloqueio de download de anexo de outra empresa (404), extensões perigosas recusadas (`.php`), tipo polimórfico arbitrário recusado no upload, conteúdo digitado escapado (XSS) e presença do middleware de CSRF `PreventRequestForgery` do Laravel 13 no grupo `web` (o Laravel desliga a checagem de CSRF durante os testes, por isso o teste verifica a configuração). Usa `Storage::fake('local')` e `UploadedFile::fake()`. |
| [`Settings/UserManagementTest.php`](../tests/Feature/Settings/UserManagementTest.php) | Cadastro de usuário com convite por e-mail, vínculo de usuário que já existe em outra empresa, ativar/desativar acesso, não desativar a si mesmo, perfil personalizado com permissões. |
| [`Tenancy/CompanyIsolationTest.php`](../tests/Feature/Tenancy/CompanyIsolationTest.php) | **Isolamento multiempresa**: listagem só da empresa ativa, 404 ao acessar registro de outra empresa pela URL (GET/PUT/DELETE), pagamento em título de outra empresa, IDs de outra empresa em formulários, troca para empresa sem vínculo, usuário em duas empresas, Global Scope preenchendo `company_id` e impedindo mover registro, `X-Company-Id` sem acesso na API, vínculo desativado. |
| [`SmokeTest.php`](../tests/Feature/SmokeTest.php) | **Teste de fumaça**: com os dados demonstrativos completos, todas as telas principais abrem (HTTP 200) para o administrador; o colaborador recebe 403 nas áreas restritas; o superadministrador acessa a gestão de empresas. |

---

## 7. Como interpretar falhas

Uma falha do Pest mostra: o arquivo e a descrição do teste, a expectativa que falhou, os valores esperado × recebido e o trecho do código. Exemplos comuns e o que significam:

| Mensagem | Causa provável | O que fazer |
|---|---|---|
| `Expected response status code [200] but received 403.` | A Policy negou: perfil sem a permissão, ou registro não pertence à empresa ativa. | Confira o perfil usado no `actingAsRole()` e a matriz de permissões (`config/permissions.php`). |
| `Expected response status code [200] but received 404.` | O `CompanyScope` não encontrou o registro: foi criado em outra empresa (ou sem `setCurrentCompany`). | Garanta que os dados foram criados na empresa do usuário autenticado. |
| `Expected response status code [200] but received 500.` | Exceção no servidor. | Leia o log: `storage/logs/laravel.log`, ou use `$response->dump()` / `->assertOk()` com `withoutExceptionHandling()` para ver a exceção real. |
| `Session is missing expected key [errors].` / `assertSessionHasErrors` falhou | A validação passou quando deveria falhar (ou falhou em outro campo). | Use `->assertSessionHasErrors()` sem argumentos e `dump(session('errors'))` para ver quais campos falharam. |
| `Attempted to lazy load [relação] on model [...] but lazy loading is disabled.` | `Model::preventLazyLoading()` está ativo fora de produção: uma view/código acessou uma relação não carregada (N+1). | Adicione `->with('relacao')` na consulta ou `->load()` no Controller. |
| `Não é possível criar X sem uma empresa ativa.` (`RuntimeException`) | Model multiempresa criado sem empresa ativa. | Chame `actingAsRole()` ou `setCurrentCompany()` antes. |
| `SQLSTATE[23505]: Unique violation` | Índice UNIQUE do banco foi violado — às vezes é exatamente o que o teste quer provar (`->throws(UniqueConstraintViolationException::class)`). | Se não era esperado, verifique dados duplicados nas factories (ex.: `document`, `code`). |
| `SQLSTATE[23514]: Check violation` | Uma CHECK constraint foi violada (ex.: `paid_cents <= amount_cents`). | A regra de negócio deixou passar um valor inválido: corrija a Action, não a constraint. |
| `database "nexus_erp_testing" does not exist` | O banco de testes não foi criado. | Veja a [seção 3](#3-o-banco-de-testes-isolado). |
| `Connection refused` (porta 5433) | O PostgreSQL do Docker não está rodando. | `docker compose up -d`. |

Ferramentas úteis durante a depuração:

```php
$response->dump();                    // imprime o corpo da resposta
$response->dumpSession();             // imprime a sessão (erros de validação, flash)
$this->withoutExceptionHandling();    // a exceção real "estoura" no teste em vez de virar 500
dd($model->fresh()->toArray());       // inspeciona o estado no banco
```

Para rodar só o teste que está falhando, use `--filter` com um trecho da descrição, e acrescente `->only()` temporariamente ao `it(...)` se preferir (lembre-se de remover).

---

## 8. Exemplos comentados

### 8.1 Teste unitário: `Money`

```php
// tests/Unit/MoneyTest.php
describe('Money::parse', function () {
    it('converte textos digitados em centavos sem usar float', function (string $input, int $expected) {
        expect(Money::parse($input))->toBe($expected);
    })->with([
        ['1.234,56', 123456],
        ['R$ 1.234,56', 123456],
        ['0,1', 10],
        ['10', 1000],
        ['1234.56', 123456],
        ['1.234', 123400],
        ['-5,25', -525],
    ]);

    it('rejeita valores inválidos', function (string $input) {
        Money::parse($input);
    })->with(['abc', '1,234,5', '12,345'])->throws(InvalidArgumentException::class);

    it('soma 0,10 + 0,20 exatamente como 0,30 (o clássico problema do float)', function () {
        expect(Money::parse('0,10') + Money::parse('0,20'))->toBe(Money::parse('0,30'));
    });
});
```

O que observar:

- `describe()` agrupa testes de um mesmo método.
- `->with([...])` é um **dataset**: o mesmo teste roda uma vez para cada linha, e cada linha aparece como um teste separado no relatório.
- `->throws(...)` declara que o teste **deve** lançar a exceção.
- `toBe()` compara com `===` (tipo e valor): `123456` (int) é diferente de `'123456'` (string). Para dinheiro, isso é exatamente o que queremos.

### 8.2 Feature de uma Action financeira

```php
// tests/Feature/Finance/PaymentTest.php
beforeEach(function () {
    actingAsRole('finance');
    $this->account = FinancialAccount::factory()->withBalance(100_000)->create();
    $this->receivable = Receivable::factory()->create(['amount_cents' => 50_000]);
});

it('é idempotente: a mesma chave não gera dois pagamentos', function () {
    $key = (string) Str::uuid();
    $action = app(RecordPayment::class);

    $first = $action->handle($this->receivable, paymentData($this->account, 10_000, $key));
    $second = $action->handle($this->receivable, paymentData($this->account, 10_000, $key));

    expect($second->id)->toBe($first->id)
        ->and(Payment::count())->toBe(1)
        ->and($this->receivable->fresh()->paid_cents)->toBe(10_000)
        ->and($this->account->fresh()->current_balance_cents)->toBe(110_000);
});
```

- `beforeEach()` roda antes de cada teste do arquivo; `$this->...` guarda dados compartilhados.
- `app(RecordPayment::class)` resolve a Action pelo **Service Container**, injetando as dependências do construtor (`LedgerService`, `AuditLogger`) — igual ao que acontece no Controller.
- O teste chama a Action **diretamente**, sem HTTP: é mais rápido e foca na regra. A camada HTTP é testada em outros testes.
- `->fresh()` recarrega o Model do banco. Sem isso, você estaria olhando o objeto em memória, que pode não refletir o que foi gravado.
- `100_000` é só um inteiro com separador visual (R$ 1.000,00 em centavos).

Outro exemplo do mesmo arquivo prova que uma falha no meio da operação desfaz **tudo** (transação):

```php
expect(fn () => app(RecordPayment::class)->handle($expensive, paymentData($this->account, 500_000)))
    ->toThrow(BusinessRuleException::class, 'Saldo insuficiente');

// A transação foi desfeita por completo: nada foi gravado.
expect($expensive->fresh()->paid_cents)->toBe(0)
    ->and($this->account->fresh()->current_balance_cents)->toBe(70_000);
```

### 8.3 Teste HTTP com `actingAsRole`

```php
// tests/Feature/Authorization/RolePermissionTest.php
it('perfil comercial cria propostas, mas não acessa o financeiro', function () {
    actingAsRole('sales', $this->company);

    $this->get('/propostas/create')->assertOk();
    $this->get('/crm/funil')->assertOk();
    $this->get('/financeiro/receber')->assertForbidden();
    $this->get('/financeiro/contas')->assertForbidden();
    $this->get('/configuracoes/auditoria')->assertForbidden();
});
```

Aqui a requisição passa por **toda** a pilha: middlewares (`auth`, `verified`, `EnsureUserIsActive`, `SetCurrentCompany`), Route Model Binding, Controller/Livewire e Policy. É o tipo de teste que prova que a proteção está no backend.

Um teste de formulário web verifica redirecionamento e erros de validação na sessão:

```php
// tests/Feature/Customers/CustomerCrudTest.php
it('impede CPF/CNPJ duplicado na mesma empresa, mas permite em outra', function () {
    $this->post('/clientes', customerPayload());
    $this->post('/clientes', customerPayload(['name' => 'Outro']))
        ->assertSessionHasErrors(['document' => 'Já existe um cadastro com este CPF/CNPJ nesta empresa.']);

    $other = createCompany();
    app(CurrentCompany::class)->run($other, fn () => Customer::factory()->create(['document' => '11222333000181']));

    expect(Customer::query()->withoutGlobalScopes()->where('document', '11222333000181')->count())->toBe(2);
});
```

### 8.4 Isolamento multiempresa

```php
// tests/Feature/Tenancy/CompanyIsolationTest.php
it('responde 404 ao acessar registro de outra empresa pela URL', function (string $method, string $suffix) {
    $this->actingAs($this->userA)
        ->call($method, "/clientes/{$this->customerB->id}{$suffix}", ['name' => 'Hackeado', 'type' => 'company', 'document' => '11222333000181', 'status' => 'active'])
        ->assertNotFound();

    expect($this->customerB->fresh()->name)->toBe('Cliente Secreto da B');
})->with([
    ['GET', ''],
    ['GET', '/edit'],
    ['PUT', ''],
    ['DELETE', ''],
]);
```

Note dois detalhes: o dataset cobre quatro verbos HTTP com um único teste, e a última linha confere que **nada mudou no banco** — não basta o status 404, o dado precisa estar intacto.

### 8.5 API com token

```php
// tests/Feature/Api/ApiTest.php
function apiToken(\App\Models\User $user): string
{
    return $user->createToken('testes')->plainTextToken;
}

it('registra pagamento de forma idempotente pela API', function () {
    setCurrentCompany($this->company);
    $receivable = Receivable::factory()->create(['amount_cents' => 50000]);
    $account = FinancialAccount::factory()->withBalance(0)->create();
    $token = apiToken($this->user);
    $payload = ['financial_account_id' => $account->id, 'amount_cents' => 20000, 'paid_on' => today()->toDateString(), 'method' => 'pix', 'idempotency_key' => (string) Str::uuid()];

    $this->withToken($token)->postJson("/api/v1/receivables/{$receivable->id}/payments", $payload)->assertCreated();
    // Retry da mesma requisição (ex.: timeout de rede): devolve o mesmo pagamento com 200.
    $this->withToken($token)->postJson("/api/v1/receivables/{$receivable->id}/payments", $payload)->assertOk();

    expect(Payment::count())->toBe(1);
    // ...
});
```

- `withToken()` envia `Authorization: Bearer <token>`.
- `postJson()`/`getJson()` enviam `Accept: application/json`, então erros chegam como JSON.
- Asserções úteis: `assertJsonPath('data.status.value', 'partial')`, `assertJsonCount(2, 'data')`, `assertJsonStructure([...])`, `assertJsonValidationErrors('document')`.

### 8.6 Componente Livewire

```php
// tests/Feature/Livewire/LivewireComponentsTest.php
it('filtra clientes pela busca em tempo real', function () {
    actingAsRole('sales');
    Customer::factory()->create(['name' => 'Padaria Pão Quente', 'trade_name' => null]);
    Customer::factory()->create(['name' => 'Oficina Motor Bom', 'trade_name' => null]);

    Livewire::test(CustomerIndex::class)
        ->assertSee('Padaria Pão Quente')
        ->set('search', 'Oficina')
        ->assertSee('Oficina Motor Bom')
        ->assertDontSee('Padaria Pão Quente');
});

it('perfil sem permissão não move oportunidades', function () {
    actingAsRole('collaborator');

    Livewire::test(Pipeline::class)->assertForbidden();
});
```

`Livewire::test()` monta o componente sem navegador: `set()` altera uma propriedade pública (como se o usuário digitasse no `wire:model`), `call()` executa um método (como um `wire:click`) e `assertSee()` verifica o HTML renderizado.

### 8.7 Teste de fumaça

```php
// tests/Feature/SmokeTest.php
beforeEach(function () {
    AuditLogger::withoutAuditing(fn () => $this->seed(DemoDataSeeder::class));
    auth()->forgetUser();
    app(CurrentCompany::class)->set(null);
});

it('abre todas as telas principais para o administrador da empresa', function () {
    // ... monta a lista de URLs com IDs reais ...
    foreach ($urls as $url) {
        $response = $this->get($url);
        $status = $response->baseResponse->getStatusCode();

        expect($status)->toBe(200, "Falha ao abrir {$url}: HTTP {$status}");
    }
});
```

É um teste "largo e raso": não verifica regras, só que nada quebra. Ele pega erros de Blade, rotas quebradas e **N+1** (graças ao `preventLazyLoading`, um relacionamento não carregado vira exceção → 500). A mensagem personalizada no `toBe()` mostra qual URL falhou.

### 8.8 Outras ferramentas usadas na suíte

```php
Notification::fake();                                         // intercepta notificações
Notification::assertSentTo($owner, ProposalStatusChangedNotification::class);

$this->travelTo(CarbonImmutable::parse('2026-10-14'));       // congela "hoje" (RecurringBillingTest)

$this->artisan('finance:verify-balances')->assertFailed();    // testa comandos Artisan
$this->artisan('erp:daily-routines', ['--sync' => true, '--company' => currentCompany()->id])->assertSuccessful();

RateLimiter::clear('vendas@exemplo.com.br|127.0.0.1');        // zera o limitador antes do teste

Storage::fake('local');                                       // disco em memória (SecurityTest)
UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf');
$this->get("/anexos/{$attachment->id}")->assertDownload('contrato.pdf');
```

---

## 9. Escrevendo um teste novo, passo a passo

Exemplo: garantir que o perfil **Atendimento** (`support`) **não** consegue cancelar uma conta a receber.

**Passo 1 — Escolha o tipo e o lugar.** É uma regra de autorização via HTTP → teste de Feature. Pode entrar em `tests/Feature/Authorization/RolePermissionTest.php` ou num arquivo novo. Para criar um arquivo novo, use o Artisan (sem incluir `Feature/` no nome):

```bash
php artisan make:test --pest ReceivableCancellationTest
```

Mova-o para a subpasta adequada (ex.: `tests/Feature/Finance/`) se quiser manter a organização por módulo.

**Passo 2 — Prepare o cenário (Arrange).** Use os helpers e factories:

```php
<?php

use App\Enums\BillStatus;
use App\Models\Receivable;

it('perfil de atendimento não cancela contas a receber', function () {
    actingAsRole('support');
    $receivable = Receivable::factory()->create();
```

**Passo 3 — Execute a ação (Act)** pelo mesmo caminho que o usuário usaria (a rota):

```php
    $response = $this->patch("/financeiro/receber/{$receivable->id}/cancelar", [
        'reason' => 'Tentativa sem permissão',
    ]);
```

**Passo 4 — Verifique o resultado (Assert)**: a resposta **e** o estado do banco:

```php
    $response->assertForbidden();

    expect($receivable->fresh()->status)->toBe(BillStatus::Open);
});
```

**Passo 5 — Rode só esse teste**, veja-o passar e depois **faça-o falhar de propósito** (ex.: troque `support` por `finance`) para ter certeza de que ele testa o que você pensa. Um teste que nunca falha não protege nada.

```bash
php artisan test --compact --filter="não cancela contas a receber"
```

**Passo 6 — Rode o arquivo inteiro / a pasta** afetada e, por fim, peça para rodar a suíte completa antes de integrar a mudança.

Checklist mental para cada funcionalidade nova:

- [ ] Caminho feliz (a operação funciona e grava o que deve).
- [ ] Validação (campos obrigatórios, formatos, `exists` restrito à empresa).
- [ ] Regras de negócio (transições inválidas, limites, duplicidade).
- [ ] Permissão (perfil sem permissão → 403).
- [ ] Isolamento (registro de outra empresa → 404; ID de outra empresa no formulário → erro de validação).

---

## 10. Boas práticas adotadas

**Teste a regra de negócio na Action.** As regras vivem nas Actions (`app/Actions/**`). Testá-las diretamente é rápido, preciso e independe da tela: `app(ConvertProposalToContract::class)->handle(...)`. A camada HTTP ganha testes próprios, mais enxutos (status, redirecionamento, validação).

**Teste o backend da permissão, não o botão.** `assertDontSee('Excluir')` prova apenas que o botão sumiu. O que protege o sistema é `->delete(...)->assertForbidden()` + conferir que o registro continua no banco.

**Não dependa de dados aleatórios.** As factories usam Faker, mas o teste não pode depender do valor sorteado:

```php
// Ruim: o nome sorteado pode conter "Oficina" e o teste falha "às vezes".
Customer::factory()->count(3)->create();
Livewire::test(CustomerIndex::class)->set('search', 'Oficina')->assertSee(...);

// Bom: os dados que importam para a asserção são explícitos.
Customer::factory()->create(['name' => 'Oficina Motor Bom', 'trade_name' => null]);
```

Repare no `'trade_name' => null` dos testes reais: o nome fantasia gerado pelo Faker também aparece na tela e poderia interferir no `assertDontSee`. O mesmo vale para datas: use `travelTo()` quando a regra depende de "hoje" (vencimentos, competências, validade de propostas).

**Valores monetários sempre em centavos inteiros** nos testes também: `'amount_cents' => 50_000`, nunca `500.00`.

**Confira o banco, não só a resposta.** Use `->fresh()`, `Model::count()` e `assertDatabaseHas()` para provar o efeito colateral (ou a ausência dele).

**Um comportamento por teste, com descrição em português** que explique a regra: `it('não permite pagar mais que o saldo em aberto')`. A lista de testes vira uma documentação viva das regras de negócio.

**Use `Notification::fake()`, `Queue::fake()`, `Event::fake()`** quando o teste não for sobre o efeito colateral em si, e asserções `assertSentTo`/`assertPushed`/`assertDispatched` quando for.

**Não apague nem enfraqueça testes para "fazer passar".** Se uma mudança quebrou um teste, ou a mudança está errada, ou a regra mudou — e aí o teste deve ser atualizado conscientemente.

---

## 11. Referências

- Pest: [Writing Tests](https://pestphp.com/docs/writing-tests) · [Expectations](https://pestphp.com/docs/expectations) · [Datasets](https://pestphp.com/docs/datasets) · [Hooks (beforeEach)](https://pestphp.com/docs/hooks) · [Filtering Tests](https://pestphp.com/docs/filtering-tests)
- Laravel: [Testing: Getting Started](https://laravel.com/docs/13.x/testing) · [HTTP Tests](https://laravel.com/docs/13.x/http-tests) · [Database Testing](https://laravel.com/docs/13.x/database-testing) · [Mocking (fakes)](https://laravel.com/docs/13.x/mocking) · [Console Tests](https://laravel.com/docs/13.x/console-tests)
- Livewire: [Testing](https://livewire.laravel.com/docs/testing)
- Relacionados no projeto: [07 — Autenticação e permissões](07-autenticacao-e-permissoes.md) · [09 — Como adicionar uma funcionalidade](09-como-adicionar-uma-funcionalidade.md) (inclui testes Pest de um módulo novo)
