# 05 — Eloquent ORM no Nexus ERP

> Como os Models funcionam neste projeto, com exemplos reais: atributos, casts, accessors, relacionamentos, scopes, traits, eager loading, consultas agregadas, transações e travas, iteração em lote, migrations, factories e seeders.
> Para cada recurso há um link para o arquivo onde ele é usado — abra-o e leia junto.

## Sumário

1. [Anatomia de um Model](#1-anatomia-de-um-model)
2. [Atribuição em massa: `#[Fillable]` e `forceFill`](#2-atribuição-em-massa-fillable-e-forcefill)
3. [Casts: convertendo tipos automaticamente](#3-casts-convertendo-tipos-automaticamente)
4. [Accessors e mutators (`Attribute`)](#4-accessors-e-mutators-attribute)
5. [Relacionamentos usados no projeto](#5-relacionamentos-usados-no-projeto)
6. [Scopes locais e o Global Scope](#6-scopes-locais-e-o-global-scope)
7. [Traits de Model (Concerns) e eventos do Eloquent](#7-traits-de-model-concerns-e-eventos-do-eloquent)
8. [Eager loading e `preventLazyLoading`](#8-eager-loading-e-preventlazyloading)
9. [`preventSilentlyDiscardingAttributes`](#9-preventsilentlydiscardingattributes)
10. [Consultas agregadas](#10-consultas-agregadas)
11. [Transações, `lockForUpdate` e atualizações atômicas](#11-transações-lockforupdate-e-atualizações-atômicas)
12. [Migrations: criar e evoluir o esquema](#12-migrations-criar-e-evoluir-o-esquema)
13. [Iterando muitos registros: `eachById`, `each`, `lazy`](#13-iterando-muitos-registros-eachbyid-each-lazy)
14. [Factories](#14-factories)
15. [Seeders](#15-seeders)
16. [Armadilhas comuns (e como o projeto as evita)](#16-armadilhas-comuns-e-como-o-projeto-as-evita)

---

## 1. Anatomia de um Model

Um **Model** do Eloquent representa uma tabela; cada instância representa uma linha. [`Customer`](../app/Models/Customer.php) mostra quase todos os elementos usados no projeto:

```php
// app/Models/Customer.php
#[Fillable([                                                      // (1) colunas preenchíveis em massa
    'type', 'name', 'trade_name', 'document', 'state_registration', 'email',
    'phone', 'mobile', 'website', 'status', 'notes', 'owner_id',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, BelongsToCompany, HasAttachments, HasContactDetails,   // (2) traits de comportamento
        HasFactory, HasRelationshipHistory, IsBusinessParty, SoftDeletes;

    protected function casts(): array                                     // (3) conversão de tipos
    {
        return [
            'type' => PersonType::class,
            'status' => CustomerStatus::class,
        ];
    }

    public function owner(): BelongsTo                                    // (4) relacionamentos
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeActive(Builder $query): void                     // (5) scope local
    {
        $query->where('status', CustomerStatus::Active);
    }
}
```

Convenções que o Eloquent aplica sozinho (por isso não aparecem no código): a tabela é o plural *snake_case* do nome da classe (`Customer` → `customers`), a chave primária é `id`, e `created_at`/`updated_at` são mantidos automaticamente.

## 2. Atribuição em massa: `#[Fillable]` e `forceFill`

### 2.1 `#[Fillable]`

"Atribuição em massa" é preencher vários atributos de uma vez: `Customer::create($dados)` ou `$customer->fill($dados)`. Se qualquer coluna pudesse ser preenchida assim, um usuário mal-intencionado poderia adicionar `company_id=2` ou `paid_cents=999999` ao formulário. A lista **`#[Fillable([...])]`** (atributo PHP do Laravel 13, equivalente à antiga propriedade `$fillable`) define o que é permitido.

Repare no que **não** está nas listas:

- [`Receivable`](../app/Models/Receivable.php): `status`, `paid_cents`, `paid_at`, `cancelled_*` ficam de fora. O valor pago só muda por `IsBill::applyPayment()`/`revertPayment()`, chamados pelas Actions.
- [`FinancialAccount`](../app/Models/FinancialAccount.php): `current_balance_cents` fica de fora — só o `LedgerService` altera o saldo.
- Nenhum Model de negócio tem `company_id` fillable — ele é preenchido pelo trait `BelongsToCompany`.

[`User`](../app/Models/User.php) também usa `#[Hidden(['password', 'remember_token'])]`: esses campos nunca aparecem quando o Model é convertido em array/JSON.

### 2.2 `forceFill()`: quando o sistema (e não o usuário) define o valor

Colunas controladas pelo sistema são gravadas com `forceFill()`, que ignora a lista de fillable. Isso deixa explícito no código: *"este valor vem da regra de negócio, não do formulário"*.

```php
// app/Actions/Proposals/ChangeProposalStatus.php
match ($target) {
    ProposalStatus::Sent => $locked->forceFill(['sent_at' => now()]),
    ProposalStatus::Approved => $locked->forceFill(['approved_at' => now(), 'approved_by' => Auth::id()]),
    ProposalStatus::Rejected => $locked->forceFill(['rejected_at' => now(), 'rejection_reason' => $reason]),
    // ...
};
```

```php
// app/Actions/Finance/CreateFinancialAccount.php
$account = new FinancialAccount($data);                                   // fill com dados validados
$account->current_balance_cents = (int) ($data['opening_balance_cents'] ?? 0);   // atribuição direta
$account->save();
```

Atribuir uma propriedade diretamente (`$model->coluna = valor`) também não passa pelo fillable.

## 3. Casts: convertendo tipos automaticamente

O método `casts()` diz ao Eloquent como converter cada coluna ao **ler** do banco e ao **gravar**. Tipos usados no projeto:

| Cast | Exemplo | Resultado em PHP |
|---|---|---|
| Enum (`ProposalStatus::class`) | `'status' => ProposalStatus::class` em [`Proposal`](../app/Models/Proposal.php) | `$proposal->status` é um `ProposalStatus`, com `->label()`, `->canTransitionTo()`... |
| `'integer'` | `'amount_cents' => 'integer'` | `int` (o PDO do PostgreSQL pode devolver bigint como string) |
| `'decimal:3'` | `'quantity' => 'decimal:3'` em [`ProposalItem`](../app/Models/ProposalItem.php) | **string** exata `"1.500"` — nunca float |
| `'date'` | `'due_date' => 'date'` | `Carbon` (meia-noite) |
| `'datetime'` | `'approved_at' => 'datetime'` | `Carbon` com hora |
| `'boolean'` | `'is_active' => 'boolean'` | `true`/`false` |
| `'hashed'` | `'password' => 'hashed'` em `User` | a senha é criptografada (bcrypt) automaticamente ao atribuir |
| `'array'` | `'old_values' => 'array'` em [`AuditLog`](../app/Models/AuditLog.php) | array PHP ↔ JSON (`jsonb`) |

Por que enums como cast? Comparações ficam seguras e legíveis (`$bill->status === BillStatus::Paid`), e o Model nunca carrega um status inexistente. Ao gravar, o Eloquent salva o `->value` (`'paid'`). Em consultas, pode-se passar o enum direto: `->where('status', ContractStatus::Active)`.

**Casts adicionados por um trait.** [`IsBill`](../app/Models/Concerns/IsBill.php) é usado por `Receivable` e `Payable`. Em vez de repetir os casts nos dois Models, o trait usa um *initializer* (método `initialize{NomeDoTrait}`, chamado pelo Eloquent em cada nova instância) e `mergeCasts()`:

```php
// app/Models/Concerns/IsBill.php
public function initializeIsBill(): void
{
    // Valores padrão também em memória (não só no banco), para que um título
    // recém-criado já tenha status e valor pago corretos sem recarregar.
    $this->attributes = array_merge(['status' => BillStatus::Open->value, 'paid_cents' => 0], $this->attributes);

    $this->mergeCasts([
        'status' => BillStatus::class,
        'amount_cents' => 'integer',
        'paid_cents' => 'integer',
        'due_date' => 'date',
        'competence' => 'date',
        // ...
    ]);
}
```

O comentário explica um detalhe útil: o banco tem `DEFAULT 'open'`, mas logo após o `create()` o objeto PHP não sabe disso (o Eloquent não relê a linha). Definir o padrão também em memória evita um `refresh()`. [`CompanySetting`](../app/Models/CompanySetting.php) faz o mesmo com a propriedade `$attributes`.

## 4. Accessors e mutators (`Attribute`)

Um **accessor** cria um atributo "virtual" calculado; um **mutator** transforma o valor antes de gravar. Ambos são métodos `protected` que retornam `Attribute`:

```php
// app/Models/Concerns/IsBusinessParty.php
protected function displayName(): Attribute
{
    return Attribute::get(fn (): string => $this->trade_name ?: $this->name);
}

protected function formattedDocument(): Attribute
{
    return Attribute::get(fn (): string => BrazilianDocument::format($this->document));
}

/**
 * Garante que o documento seja sempre gravado sem máscara.
 */
protected function document(): Attribute
{
    return Attribute::set(fn (?string $value): string => BrazilianDocument::clean($value));
}
```

O método em *camelCase* vira um atributo em *snake_case*: `displayName()` → `$customer->display_name`, `formattedDocument()` → `$customer->formatted_document`. O mutator `document()` roda a cada atribuição: `$customer->document = '123.456.789-09'` grava `12345678909`.

Outros exemplos: `Company::displayName()` (fantasia ou razão social) e `Company::logoUrl()` (gera a rota autenticada do logotipo).

Quando o cálculo depende de regra ou recebe parâmetros, o projeto usa **métodos comuns** em vez de accessors: `IsBill::outstandingCents()`, `IsBill::isOverdue()`, `Contract::annualValueCents()`, `Opportunity::weightedValueCents()`.

## 5. Relacionamentos usados no projeto

### 5.1 `belongsTo` — "este registro aponta para outro"

```php
// app/Models/Receivable.php
public function customer(): BelongsTo
{
    return $this->belongsTo(Customer::class)->withTrashed();
}
```

A FK fica **nesta** tabela (`receivables.customer_id`). O `->withTrashed()` é uma decisão de negócio: o cliente pode ter sido excluído (soft delete), mas a cobrança antiga continua mostrando de quem era. FK com nome fora da convenção é passada como segundo argumento: `$this->belongsTo(User::class, 'owner_id')`.

### 5.2 `hasMany` — "um para muitos"

```php
// app/Models/Contract.php
public function items(): HasMany
{
    return $this->hasMany(ContractItem::class)->orderBy('position');
}
```

A FK fica na **outra** tabela (`contract_items.contract_id`). A ordenação faz parte da definição: `$contract->items` sempre vem na ordem certa.

Uma variação interessante em [`IsBill`](../app/Models/Concerns/IsBill.php): o mesmo trait serve a `Receivable` e `Payable`, cuja FK em `payments` tem nomes diferentes. Cada Model declara uma constante e o trait a usa:

```php
// app/Models/Receivable.php
public const string PAYMENT_FOREIGN_KEY = 'receivable_id';

// app/Models/Concerns/IsBill.php
public function payments(): HasMany
{
    return $this->hasMany(Payment::class, static::PAYMENT_FOREIGN_KEY)->latest('paid_on')->latest('id');
}

public function confirmedPayments(): HasMany
{
    return $this->hasMany(Payment::class, static::PAYMENT_FOREIGN_KEY)->where('status', EntryStatus::Confirmed);
}
```

### 5.3 `hasOne` — "um para um (ou zero)"

```php
// app/Models/Company.php
public function settings(): HasOne
{
    return $this->hasOne(CompanySetting::class);
}

// app/Models/Proposal.php — contrato originado por esta proposta (no máximo um)
public function contract(): HasOne
{
    return $this->hasOne(Contract::class);
}
```

O "no máximo um" de `Proposal::contract()` é garantido pelo banco (`contracts.proposal_id` UNIQUE), não pelo `hasOne` — o relacionamento só descreve como buscar.

`Company::settingsOrDefault()` mostra um padrão útil: se a relação não existe, cria com os padrões e já a deixa carregada com `setRelation()`:

```php
public function settingsOrDefault(): CompanySetting
{
    if ($this->relationLoaded('settings') && $this->settings !== null) {
        return $this->settings;
    }

    $settings = $this->settings()->firstOrCreate();
    $this->setRelation('settings', $settings);

    return $settings;
}
```

### 5.4 `belongsToMany` com Pivot customizado (`CompanyMembership`)

Usuário ↔ empresa é N:N pela tabela `company_user`, que guarda **mais** que as duas chaves: o perfil, a unidade e se o vínculo está ativo. Para isso o projeto usa um **Pivot model** próprio:

```php
// app/Models/User.php
public function companies(): BelongsToMany
{
    return $this->belongsToMany(Company::class)
        ->using(CompanyMembership::class)     // classe do pivô
        ->as('membership')                    // nome do atributo: $company->membership (em vez de ->pivot)
        ->withPivot(['id', 'role_id', 'company_unit_id', 'is_active'])
        ->withTimestamps();
}
```

```php
// app/Models/CompanyMembership.php
class CompanyMembership extends Pivot
{
    use Auditable;

    protected $table = 'company_user';

    public $incrementing = true;        // o pivô tem id próprio

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
    // company(), user(), unit()...
}
```

Por que um Pivot model? Para ter relacionamentos no próprio vínculo (`$membership->role`), casts e **auditoria** (mudar o perfil de alguém fica registrado). Filtrar pelo pivô:

```php
// app/Models/User.php
return $this->companies()
    ->wherePivot('is_active', true)
    ->where('companies.status', CompanyStatus::Active)
    ->orderBy('trade_name')
    ->get();
```

O projeto também define `memberships()` como `hasMany(CompanyMembership::class)`, útil quando o vínculo em si é o foco (ex.: `scopeMemberOf()`).

`belongsToMany` simples (sem Pivot model) aparece em `Role::permissions()` (tabela `permission_role`).

### 5.5 Polimórficos: `morphMany`, `morphOne`, `morphTo`

```php
// app/Models/Concerns/HasContactDetails.php
public function addresses(): MorphMany
{
    return $this->morphMany(Address::class, 'addressable')->orderByDesc('is_primary')->orderBy('id');
}

/**
 * Endereço principal (morphOne: retorna um único registro).
 */
public function primaryAddress(): MorphOne
{
    return $this->morphOne(Address::class, 'addressable')->where('is_primary', true);
}
```

```php
// app/Models/Address.php — o lado inverso
public function addressable(): MorphTo
{
    return $this->morphTo();
}
```

`'addressable'` é o prefixo das colunas `addressable_type`/`addressable_id`. `$customer->addresses()->create([...])` preenche as duas sozinho, gravando `addressable_type = 'customer'` (apelido do *morph map* definido em `AppServiceProvider`). Outros exemplos: `HasAttachments::attachments()`, `HasStatusHistory::statusChanges()`, `Auditable::auditLogs()`, `AuditLog::auditable()` (`morphTo`).

### 5.6 Auto-relacionamento: estorno de lançamento

Um lançamento de estorno aponta para o lançamento original na **mesma tabela** (`financial_transactions.reversal_of_id`):

```php
// app/Models/FinancialTransaction.php
/** Lançamento original que este estorno anula. */
public function reversalOf(): BelongsTo
{
    return $this->belongsTo(self::class, 'reversal_of_id');
}

/** Estorno deste lançamento, se houver. */
public function reversal(): HasOne
{
    return $this->hasOne(self::class, 'reversal_of_id');
}
```

O `LedgerService` usa o `hasOne` para impedir estorno duplo antes de tentar gravar (o índice UNIQUE em `reversal_of_id` garante o mesmo no banco):

```php
// app/Services/Finance/LedgerService.php
if ($original->reversal()->exists()) {
    throw new BusinessRuleException('Este lançamento já foi estornado.');
}
```

### 5.7 Propriedade × método

- `$contract->items` (propriedade) executa a consulta **uma vez** e guarda o resultado (Collection).
- `$contract->items()` (método) devolve um *query builder* para continuar a consulta: `$customer->receivables()->overdue()->sum(...)`, `$proposal->items()->exists()`.

## 6. Scopes locais e o Global Scope

### 6.1 Scopes locais — consultas reutilizáveis com nome

Um método `scopeNome(Builder $query, ...)` vira `->nome(...)` nas consultas:

```php
// app/Models/Concerns/IsBill.php
/**
 * Títulos vencidos: em aberto/parciais com vencimento anterior a hoje.
 */
public function scopeOverdue(Builder $query): void
{
    $query->whereIn('status', BillStatus::outstanding())->whereDate('due_date', '<', Carbon::today());
}
```

```php
// uso em app/Jobs/NotifyOverdueReceivablesJob.php
Receivable::query()->overdue()->selectRaw('COUNT(*) AS total_count, ...')->first();
```

Outros scopes do projeto: `scopeActive()` (vários Models), `scopeSearch()` (busca com `ILIKE`/`whereAny`), `scopeOutstanding()`, `scopeUpcoming()`, `scopeDueBetween()` (IsBill), `User::scopeMemberOf()`, `PipelineStage::scopeOrdered()`, `Task::scopePending()`, `ServiceOrder::scopePending()`.

O `scopeSearch()` de [`IsBusinessParty`](../app/Models/Concerns/IsBusinessParty.php) mostra boas práticas: agrupa as condições `OR` dentro de um `where(function ...)` (para não "escapar" do filtro de empresa) e usa *bindings*:

```php
$query->where(function (Builder $query) use ($term, $document): void {
    $query->whereAny(['name', 'trade_name', 'email'], 'ilike', "%{$term}%");

    if (strlen($document) >= 3) {
        $query->orWhere('document', 'like', "%{$document}%");
    }
});
```

Sem o `where(function ...)`, o SQL seria `... company_id = 1 AND name ILIKE ... OR document LIKE ...` — e o `OR` traria clientes de **outras empresas**.

### 6.2 O Global Scope `CompanyScope`

Um **Global Scope** é aplicado automaticamente a **todas** as consultas de um Model. O projeto tem um só, o [`CompanyScope`](../app/Models/Scopes/CompanyScope.php), adicionado pelo trait `BelongsToCompany` (detalhes em [02 — Arquitetura](02-arquitetura.md#6-estratégia-multiempresa-tenancy)). Para desligá-lo conscientemente:

```php
// app/Console/Commands/VerifyAccountBalances.php — confere TODAS as empresas
FinancialAccount::query()->withoutGlobalScope(CompanyScope::class)->with('company')->orderBy('company_id')->orderBy('id')
```

Ou com o scope local `forCompanies()`, que remove o global e filtra por uma lista explícita (usado pelo dashboard consolidado).

`SoftDeletes` também é um Global Scope (adiciona `deleted_at IS NULL`); `->withTrashed()` o desliga.

## 7. Traits de Model (Concerns) e eventos do Eloquent

### 7.1 `boot{Trait}` e `initialize{Trait}`

O Eloquent chama automaticamente, para cada trait usado:

- `bootNomeDoTrait()` — **uma vez por classe**: ideal para registrar Global Scopes e ouvintes de eventos (`creating`, `updated`...).
- `initializeNomeDoTrait()` — **a cada nova instância**: ideal para casts e valores padrão.

```php
// app/Models/Concerns/Auditable.php
public static function bootAuditable(): void
{
    static::created(function (Model $model): void {
        app(AuditLogger::class)->log('created', $model, null, $model->auditableAttributes($model->getAttributes()));
    });

    static::updated(function (Model $model): void {
        $changes = $model->auditableAttributes($model->getChanges());

        if ($changes === []) {
            return;
        }

        $original = array_intersect_key($model->getOriginal(), $changes);

        app(AuditLogger::class)->log('updated', $model, $model->auditableAttributes($original), $changes);
    });
    // deleted, restored...
}
```

Note o uso de `getChanges()` (o que mudou no último `save()`) e `getOriginal()` (valores antes da mudança). Um Model pode ignorar colunas na auditoria com `$auditExclude` (ex.: `Opportunity` ignora `position`, que muda a cada arrastar no Kanban).

### 7.2 Eventos no próprio Model: imutabilidade

```php
// app/Models/FinancialTransaction.php
protected static function booted(): void
{
    static::updating(fn () => throw new LogicException('Lançamentos financeiros são imutáveis. Utilize um estorno.'));
    static::deleting(fn () => throw new LogicException('Lançamentos financeiros não podem ser excluídos. Utilize um estorno.'));
}
```

`booted()` é o gancho para eventos específicos de um Model (sem trait).

### 7.3 Quando os eventos NÃO disparam

Eventos do Eloquent só disparam em operações **sobre instâncias** (`$model->save()`, `->delete()`, `Model::create()`). Operações em massa feitas pelo query builder **não** carregam os Models e **não** disparam eventos:

```php
// app/Actions/Crm/ConvertLeadToCustomer.php — UPDATE direto, sem eventos (sem auditoria por oportunidade)
Opportunity::query()->where('lead_id', $locked->id)->whereNull('customer_id')
    ->update(['customer_id' => $customer->id]);
```

Isso é rápido e às vezes desejável, mas lembre-se: nesses casos o trait `Auditable` não registra nada. Por isso a Action grava uma auditoria explícita da operação (`lead.converted`). Da mesma forma, `saveQuietly()` salva **sem** disparar eventos — usado, por exemplo, para gravar o último login sem gerar auditoria ([`RecordSuccessfulLogin`](../app/Listeners/RecordSuccessfulLogin.php)) e para atualizar `next_billing_date` na geração de cobranças.

## 8. Eager loading e `preventLazyLoading`

### 8.1 O problema N+1

```php
$customers = Customer::query()->paginate(15);      // 1 consulta

foreach ($customers as $customer) {
    echo $customer->owner->name;                    // +1 consulta POR cliente = 15 consultas
}
```

Isso é o **problema N+1**: 1 consulta para a lista + N consultas para o relacionamento. Com 500 linhas, 501 consultas.

### 8.2 A solução: `with()` e `load()`

```php
// app/Livewire/Customers/CustomerIndex.php
Customer::query()
    ->with('owner:id,name')      // carrega TODOS os responsáveis em 1 consulta extra (só id e name)
```

`with()` faz o *eager loading* na consulta; `load()` faz o mesmo num Model já carregado. A tela de detalhes do cliente carrega vários relacionamentos de uma vez, alguns com **restrições**:

```php
// app/Http/Controllers/Customers/CustomerController.php (show)
$customer->load([
    'owner', 'addresses', 'contacts', 'originLead.source',      // aninhado: lead e a origem do lead
    'interactions.user', 'tasks.assignee',
    'attachments.uploader',
    'opportunities' => fn ($query) => $query->with('stage')->latest()->limit(10),
    'proposals' => fn ($query) => $query->latest()->limit(10),
    // ...
]);
```

`loadMissing()` carrega só o que ainda não foi carregado (usado em [`SendProposalStatusNotification`](../app/Listeners/SendProposalStatusNotification.php): `$event->proposal->loadMissing(['owner', 'company.settings'])`).

### 8.3 `preventLazyLoading`: transformando o erro silencioso em exceção

```php
// app/Providers/AppServiceProvider.php
// Em desenvolvimento, falha ao acessar relacionamentos não carregados
// (lazy loading): ajuda a detectar problemas de N+1 queries cedo.
Model::preventLazyLoading(! $this->app->isProduction());
```

Em desenvolvimento e nos testes, acessar `$customer->owner` sem ter feito `with('owner')` **lança uma exceção**, em vez de executar uma consulta silenciosa. O N+1 aparece na hora, não em produção com milhares de linhas. Em produção a proteção fica desligada (para não derrubar a tela de um usuário por um detalhe de desempenho).

Detalhe importante do Laravel: a proteção só vale para Models que vieram **de uma consulta com mais de um resultado** (é aí que o N+1 acontece). Um Model buscado sozinho (`Receivable::findOrFail($id)`) pode carregar relações sob demanda normalmente — por isso o `LedgerService` pode acessar `$original->account` sem `with()`, enquanto [`ReverseTransfer`](../app/Actions/Finance/ReverseTransfer.php), que percorre uma coleção de lançamentos, usa `->with('account')`:

```php
$locked->transactions()->whereNull('reversal_of_id')->with('account')->get()
    ->each(fn ($entry) => $this->ledger->reverse($entry, $reason));
```

## 9. `preventSilentlyDiscardingAttributes`

```php
// app/Providers/AppServiceProvider.php
Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
```

Sem essa opção, `Customer::create(['nome' => 'Acme'])` (com a chave errada, ou com uma coluna fora do `#[Fillable]`) simplesmente **descartaria** o atributo em silêncio — e você passaria horas procurando por que o campo não grava. Com a opção ligada, o Laravel lança uma exceção em desenvolvimento, apontando o atributo descartado. É por isso que as Actions passam a `create()` apenas campos fillable e usam `forceFill()` para colunas do sistema (seção 2.2).

## 10. Consultas agregadas

Relatórios e dashboards precisam de **números**, não de Models. Carregar milhares de registros para somar em PHP seria lento; o projeto deixa o banco calcular.

### 10.1 `withCount` e `withSum` — agregados de relacionamentos

```php
// app/Livewire/Customers/CustomerIndex.php — contratos ativos de cada cliente da página
->withCount(['contracts as active_contracts_count' => fn ($query) => $query->where('status', 'active')])
```

```php
// app/Services/Dashboard/DashboardMetrics.php — quantidade e valor das oportunidades abertas por etapa
PipelineStage::query()->forCompanies($this->companyIds)
    ->where('is_won', false)->where('is_lost', false)
    ->withCount(['opportunities as open_count' => fn ($query) => $query->where('status', OpportunityStatus::Open)])
    ->withSum(['opportunities as open_value' => fn ($query) => $query->where('status', OpportunityStatus::Open)], 'estimated_value_cents')
    ->orderBy('position')
    ->get();
```

O Eloquent gera subconsultas no `SELECT` e o resultado vira um atributo (`$customer->active_contracts_count`).

### 10.2 Subconsultas com `addSelect`

Quando o agregado não é um relacionamento simples, passa-se um *query builder* como coluna:

```php
// app/Reports/CustomersReport.php
->select('customers.*')
->addSelect([
    'active_contracts' => Contract::query()
        ->selectRaw('COUNT(*)')
        ->whereColumn('contracts.customer_id', 'customers.id')
        ->where('status', ContractStatus::Active),
    'received_cents' => Receivable::query()
        ->selectRaw('COALESCE(SUM(paid_cents), 0)')
        ->whereColumn('receivables.customer_id', 'customers.id'),
    'outstanding_cents' => Receivable::query()
        ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0)')
        ->whereColumn('receivables.customer_id', 'customers.id')
        ->whereIn('status', BillStatus::outstanding()),
])
```

Uma única consulta devolve cada cliente com três números calculados — sem N+1. `whereColumn()` compara duas colunas (e não uma coluna com um valor).

### 10.3 Somas com expressões e `FILTER` do PostgreSQL

```php
// app/Http/Controllers/Customers/CustomerController.php — saldo em aberto de um cliente
$customer->receivables()->whereIn('status', BillStatus::outstanding())->sum(DB::raw('amount_cents - paid_cents'))
```

```php
// app/Reports/CommercialReport.php — várias contagens em UMA passada
Proposal::query()
    ->whereBetween('issue_date', [$filters['de'], $filters['ate']])
    ->selectRaw('COUNT(*) AS issued')
    ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS approved', [ProposalStatus::Approved->value])
    ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS rejected', [ProposalStatus::Rejected->value])
    ->selectRaw('COALESCE(SUM(total_cents) FILTER (WHERE status = ?), 0) AS approved_cents', [ProposalStatus::Approved->value]);
```

`COUNT(*) FILTER (WHERE ...)` é um recurso do PostgreSQL que conta, na mesma leitura da tabela, só as linhas que atendem a cada condição. Os valores vão como *bindings* (`?`). Quando a lista vem de constantes do código (enums), os relatórios interpolam com `Report::sqlList()`, que é seguro porque o valor nunca vem do usuário.

`->toBase()` devolve objetos simples (`stdClass`) em vez de Models — mais leve quando o resultado não é uma entidade (ex.: uma linha de totais).

### 10.4 Agrupamento por mês

```php
// app/Services/Dashboard/DashboardMetrics.php
Payment::query()->forCompanies($this->companyIds)
    ->where('status', EntryStatus::Confirmed)
    ->whereBetween('paid_on', [$this->from->toDateString(), $this->to->toDateString()])
    ->selectRaw("to_char(paid_on, 'YYYY-MM') AS month")
    ->selectRaw('SUM(CASE WHEN receivable_id IS NOT NULL THEN amount_cents ELSE 0 END) AS income')
    ->selectRaw('SUM(CASE WHEN payable_id IS NOT NULL THEN amount_cents ELSE 0 END) AS expense')
    ->groupBy('month')
    ->get()
    ->keyBy('month');
```

Depois, o PHP percorre **todos** os meses do período (inclusive os sem movimento) e preenche zero onde não há linha — assim o gráfico não "pula" meses.

## 11. Transações, `lockForUpdate` e atualizações atômicas

### 11.1 `DB::transaction()`

```php
return DB::transaction(function () use ($party, $data, $addresses, $contacts) {
    $party->fill($data)->save();
    $this->syncContactDetails->handle($party, $addresses, $contacts);

    return $party;
});
```

Se qualquer exceção for lançada dentro do *closure*, tudo é desfeito (ROLLBACK); senão, COMMIT. Transações podem ser **aninhadas**: a interna vira um *savepoint*. O projeto usa isso de propósito na geração de cobranças:

```php
// app/Actions/Contracts/GenerateContractCharges.php
try {
    // Savepoint: se o índice UNIQUE barrar, desfaz só esta inserção.
    DB::transaction(fn () => Receivable::create([...]));
} catch (UniqueConstraintViolationException) {
    return false;
}
```

No PostgreSQL, um erro dentro de uma transação "envenena" a transação inteira; o savepoint permite desfazer só a inserção que falhou e continuar o laço.

### 11.2 `lockForUpdate()` — trava pessimista

```php
// app/Actions/Finance/RecordPayment.php
$lockedBill = $bill->newQuery()->whereKey($bill->getKey())->lockForUpdate()->firstOrFail();
```

Gera `SELECT ... FOR UPDATE`: a linha fica travada até o fim da transação. Outra transação que tente travar a mesma linha **espera**. Sem isso, dois pagamentos simultâneos poderiam ler o mesmo saldo em aberto (R$ 100), ambos aprovarem R$ 100, e o título ficaria pago duas vezes.

Repare que as Actions **releem** o registro travado em vez de usar o objeto recebido: o objeto recebido pode estar desatualizado (foi carregado antes de a outra transação gravar). Ao final, sincronizam o objeto original:

```php
$bill->setRawAttributes($lockedBill->getAttributes(), sync: true);
```

**Ordem de travamento contra deadlock.** Ao travar duas contas numa transferência, [`CreateTransfer`](../app/Actions/Finance/CreateTransfer.php) trava **sempre em ordem de id**:

```php
$accounts = FinancialAccount::query()
    ->whereIn('id', [$data['from_account_id'], $data['to_account_id']])
    ->orderBy('id')
    ->lockForUpdate()
    ->get()
    ->keyBy('id');
```

Se A→B e B→A acontecerem juntas, as duas tentam travar primeiro a conta de menor id; uma espera a outra, em vez de cada uma segurar uma conta e esperar a outra para sempre (*deadlock*).

### 11.3 Atualização atômica com `increment()`

```php
// app/Services/Finance/LedgerService.php
$lockedAccount->newQuery()->whereKey($lockedAccount->id)
    ->increment('current_balance_cents', $direction->sign() * $amountCents);
```

Gera `UPDATE financial_accounts SET current_balance_cents = current_balance_cents + 50000 WHERE id = ?`. O cálculo acontece **no banco**, a partir do valor atual, e não a partir de um valor lido antes (que poderia estar velho).

### 11.4 `insertOrIgnore()` — "crie se não existir"

```php
// app/Services/DocumentNumberGenerator.php
DocumentSequence::query()->insertOrIgnore([
    'company_id' => $company->id, 'type' => $type, 'year' => $year, 'last_number' => 0,
    'created_at' => now(), 'updated_at' => now(),
]);

$sequence = DocumentSequence::query()
    ->where('company_id', $company->id)->where('type', $type)->where('year', $year)
    ->lockForUpdate()
    ->firstOrFail();

$sequence->increment('last_number');
```

`insertOrIgnore` gera `INSERT ... ON CONFLICT DO NOTHING` (apoiado no UNIQUE `(company_id, type, year)`): se dois processos criarem o contador ao mesmo tempo, nenhum falha. Em seguida a linha é travada e incrementada.

## 12. Migrations: criar e evoluir o esquema

### 12.1 Criar tabelas

Uma migration descreve a mudança no `up()` e como desfazê-la no `down()`:

```php
// database/migrations/2026_10_14_100600_create_proposals_and_contracts_tables.php (trecho)
Schema::create('proposal_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
    $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
    $table->string('description');
    // Quantidade decimal exata (ex.: 1,5 hora). Nunca float.
    $table->decimal('quantity', 12, 3);
    $table->unsignedBigInteger('unit_price_cents');
    $table->unsignedBigInteger('total_cents');
    $table->unsignedSmallInteger('position')->default(0);
    $table->timestamps();
});

DB::statement('ALTER TABLE proposal_items ADD CONSTRAINT proposal_items_quantity_check CHECK (quantity > 0)');
```

Recursos que o *Schema Builder* não cobre (CHECK constraints, índices parciais) são criados com `DB::statement()` em SQL do PostgreSQL. Para criar uma migration nova, use `php artisan make:migration nome_descritivo --no-interaction`.

### 12.2 Evoluir: sempre com uma **nova** migration

Depois que uma migration rodou em algum ambiente (produção, máquina de um colega), **não a edite**: o Laravel registra na tabela `migrations` que ela já foi aplicada e nunca a rodará de novo naquele banco. A alteração vai em uma nova migration. O projeto tem dois exemplos reais:

**Adicionar coluna** — [`2026_10_14_100900_add_financial_category_id_to_contracts_table.php`](../database/migrations/2026_10_14_100900_add_financial_category_id_to_contracts_table.php):

```php
public function up(): void
{
    Schema::table('contracts', function (Blueprint $table) {
        $table->foreignId('financial_category_id')->nullable()->after('amount_cents')
            ->constrained()->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('contracts', function (Blueprint $table) {
        $table->dropConstrainedForeignId('financial_category_id');
    });
}
```

A coluna nasce `nullable()` para que os contratos que já existem continuem válidos. Depois da migration, o Model ganhou `'financial_category_id'` no `#[Fillable]` e o relacionamento `Contract::category()`; a Action de cobrança passou a copiar a categoria para cada conta a receber.

**Trocar um índice** — [`2026_10_15_090000_use_partial_unique_codes_for_catalog_and_units.php`](../database/migrations/2026_10_15_090000_use_partial_unique_codes_for_catalog_and_units.php):

```php
public function up(): void
{
    Schema::table('catalog_items', fn (Blueprint $table) => $table->dropUnique(['company_id', 'code']));
    Schema::table('company_units', fn (Blueprint $table) => $table->dropUnique(['company_id', 'code']));

    DB::statement('CREATE UNIQUE INDEX catalog_items_company_code_unique ON catalog_items (company_id, code) WHERE deleted_at IS NULL');
    DB::statement('CREATE UNIQUE INDEX company_units_company_code_unique ON company_units (company_id, code) WHERE deleted_at IS NULL');
}

public function down(): void
{
    DB::statement('DROP INDEX IF EXISTS catalog_items_company_code_unique');
    DB::statement('DROP INDEX IF EXISTS company_units_company_code_unique');

    Schema::table('catalog_items', fn (Blueprint $table) => $table->unique(['company_id', 'code']));
    Schema::table('company_units', fn (Blueprint $table) => $table->unique(['company_id', 'code']));
}
```

O problema descoberto: com o UNIQUE comum, um item excluído (soft delete) impedia reutilizar o código. A correção foi um UNIQUE **parcial**, a mesma regra já usada no CPF/CNPJ. O `down()` desfaz exatamente na ordem inversa.

## 13. Iterando muitos registros: `eachById`, `each`, `lazy`

`Model::all()` ou `->get()` carregam **tudo** na memória. Para rotinas que percorrem muitos registros, o projeto usa iteração em lotes:

| Método | Como pagina | Quando usar | Exemplo |
|---|---|---|---|
| `eachById()` | por `id` (`WHERE id > último ORDER BY id LIMIT 1000`) | quando o laço **altera** os registros (e talvez a condição do filtro) | [`ExpireProposals`](../app/Actions/Proposals/ExpireProposals.php), [`GenerateContractCharges`](../app/Actions/Contracts/GenerateContractCharges.php), [`ProcessContractLifecycle`](../app/Actions/Contracts/ProcessContractLifecycle.php) |
| `each()` / `chunk()` | por `OFFSET` | leitura em que o conjunto não muda durante o laço | [`VerifyAccountBalances`](../app/Console/Commands/VerifyAccountBalances.php) |
| `lazy(500)` | por `OFFSET`, devolvendo um *generator* | exportações e relatórios grandes, linha a linha | `CustomerController::export()`, [`ContractsReport`](../app/Reports/ContractsReport.php), [`CustomersReport`](../app/Reports/CustomersReport.php) |

**Por que `eachById` nas rotinas?** Veja `ExpireProposals`:

```php
Proposal::query()
    ->where('status', ProposalStatus::Sent)
    ->whereDate('valid_until', '<', today())
    ->eachById(function (Proposal $proposal) use (&$expired): void {
        $this->changeStatus->handle($proposal, ProposalStatus::Expired, 'Validade expirada (rotina automática).');
        $expired++;
    });
```

Cada proposta processada **sai** do filtro (deixa de ser `sent`). Com paginação por `OFFSET`, o segundo lote começaria na posição 1000 de um conjunto que encolheu 1000 itens — e **pularia** registros. Paginando por `id > último processado`, nenhum registro é pulado.

Pelo mesmo motivo de estabilidade, as consultas com `lazy()` sempre têm uma ordenação com desempate único (`->orderBy('name')->orderBy('id')`, comentado em `CustomersReport`).

## 14. Factories

Factories criam Models com dados falsos para testes. Elas ficam em [`database/factories`](../database/factories) e são usadas com `Customer::factory()->create()`.

```php
// database/factories/CustomerFactory.php
class CustomerFactory extends Factory
{
    use ResolvesCompany;

    public function definition(): array
    {
        $company = fake()->company();

        return [
            'company_id' => $this->companyId(),
            'type' => PersonType::Company,
            'name' => $company.' Ltda',
            'trade_name' => $company,
            'document' => BrazilianDocument::generateCnpj(),     // CNPJ válido, gerado
            'email' => fake()->unique()->companyEmail(),
            'status' => CustomerStatus::Active,
        ];
    }

    public function individual(): static                        // "state": variação nomeada
    {
        return $this->state(fn () => [
            'type' => PersonType::Individual,
            'name' => fake()->name(),
            'trade_name' => null,
            'document' => BrazilianDocument::generateCpf(),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CustomerStatus::Inactive]);
    }
}
```

**`ResolvesCompany`** ([`database/factories/Concerns/ResolvesCompany.php`](../database/factories/Concerns/ResolvesCompany.php)) resolve o `company_id` de forma preguiçosa (um *closure* só executado se nenhum valor for informado):

```php
protected function companyId(): Closure
{
    return fn (): int => app(CurrentCompany::class)->id() ?? Company::factory()->create()->id;
}
```

Se há empresa ativa (o teste chamou `actingAsRole()`), usa ela; senão, cria uma. Também é possível informar explicitamente: `Customer::factory()->for($company)->create()`.

Atributos dependentes usam *closures* que recebem os atributos já resolvidos — garantindo que o cliente seja da **mesma** empresa do título:

```php
// database/factories/ReceivableFactory.php
'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
```

Exemplos de uso combinando *states*: `Customer::factory()->individual()->inactive()->create()`, `Receivable::factory()->overdue()->create()`.

> Factories rodam com o Model "desprotegido" (`Model::unguarded`), por isso podem preencher colunas fora do `#[Fillable]`, como `paid_cents` e `status` em `ReceivableFactory`.

Os helpers de teste em [`tests/Pest.php`](../tests/Pest.php) combinam factories e Actions: `createCompany()` cria a empresa e roda `ProvisionCompanyDefaults` (perfis, funil, categorias); `actingAsRole('finance')` cria um usuário com o perfil, autentica e define a empresa ativa.

## 15. Seeders

| Seeder | Papel |
|---|---|
| [`DatabaseSeeder`](../database/seeders/DatabaseSeeder.php) | Ponto de entrada (`php artisan migrate:fresh --seed`). Sempre roda o `PermissionSeeder`; em produção **para aí**. Em desenvolvimento, troca e-mail para `array` e fila para `sync`, e roda o `DemoDataSeeder` dentro de `AuditLogger::withoutAuditing()` (sem milhares de linhas de auditoria). |
| [`PermissionSeeder`](../database/seeders/PermissionSeeder.php) | Sincroniza a tabela `permissions` com [`config/permissions.php`](../config/permissions.php) usando `updateOrCreate` (idempotente) e remove permissões que saíram do arquivo. Rode `php artisan db:seed --class=PermissionSeeder` após alterar o arquivo. |
| [`DemoDataSeeder`](../database/seeders/DemoDataSeeder.php) | Duas empresas, usuários de todos os perfis e dados interligados. |

O ponto didático do `DemoDataSeeder` é que ele **não insere linhas soltas**: chama as mesmas Actions da aplicação (`SaveProposal`, `ConvertProposalToContract`, `ChangeContractStatus`, `RecordPayment`, `CreateTransfer`...). Assim, os dados de demonstração respeitam todas as regras de negócio — e `php artisan finance:verify-balances` confere logo após o seed.

## 16. Armadilhas comuns (e como o projeto as evita)

| Armadilha | Sintoma | Como o projeto evita |
|---|---|---|
| N+1 | Tela lenta com muitas linhas | `with()`/`withCount()` + `preventLazyLoading` em desenvolvimento |
| Esquecer o filtro de empresa | Dados de outra empresa aparecem | `CompanyScope` automático; `where('company_id', ...)` explícito em `exists`/`unique` |
| `OR` "escapando" do filtro | Busca traz registros de outras empresas | `where(function ...)` agrupando os `OR` nos `scopeSearch()` |
| Float em dinheiro | Diferenças de centavos | Colunas `*_cents` inteiras, `decimal:3` como string, `bcmath` |
| Atributo descartado em silêncio | Campo "não grava" | `preventSilentlyDiscardingAttributes` |
| Condição de corrida | Pagamento duplicado, número repetido | `lockForUpdate()`, `increment()`, índices UNIQUE, `insertOrIgnore` |
| Laço que altera o próprio filtro | Registros pulados | `eachById()` |
| Update em massa sem eventos | Auditoria faltando | Auditoria explícita na Action quando usa `query()->update()` |
| Model desatualizado após a transação | Tela mostra valor antigo | Reler com trava e `setRawAttributes(..., sync: true)` |
