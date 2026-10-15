# 11 — Decisões de arquitetura (ADRs)

Este documento registra as principais decisões técnicas do Nexus ERP no formato **ADR** (*Architecture Decision Record*). Cada registro é curto e responde a quatro perguntas:

- **Contexto** — qual problema ou força motivou a decisão?
- **Decisão** — o que foi escolhido?
- **Alternativas** — o que mais foi considerado e por que não foi escolhido?
- **Consequências** — o que ganhamos, o que custa e o que fica de atenção.

> Para o iniciante: decisões de arquitetura raramente são "certas" ou "erradas" — são **trocas** (*trade-offs*). Ler os ADRs ensina a pensar nas consequências antes de escrever código. Se uma decisão mudar no futuro, o certo é escrever um ADR novo que **substitui** o antigo, e não apagar o histórico.

## Índice

| # | Decisão | Status |
|---|---|---|
| [ADR-01](#adr-01--monólito-modular) | Monólito modular | Aceita |
| [ADR-02](#adr-02--livewire-class-based--controllers-e-não-spa) | Livewire class-based + Controllers (e não SPA) | Aceita |
| [ADR-03](#adr-03--fortify-com-telas-próprias) | Fortify com telas próprias | Aceita |
| [ADR-04](#adr-04--permissões-próprias-por-empresa-sem-spatielaravel-permission) | Permissões próprias por empresa (sem spatie/laravel-permission) | Aceita |
| [ADR-05](#adr-05--multiempresa-por-coluna-company_id--global-scope) | Multiempresa por coluna `company_id` + Global Scope | Aceita |
| [ADR-06](#adr-06--dinheiro-em-centavos-inteiros--bcmath) | Dinheiro em centavos inteiros + bcmath | Aceita |
| [ADR-07](#adr-07--actions-em-vez-de-repositories) | Actions em vez de Repositories | Aceita |
| [ADR-08](#adr-08--status-como-enum-com-máquina-de-estados) | Status como Enum com máquina de estados | Aceita |
| [ADR-09](#adr-09--livro-razão-imutável-com-saldo-materializado) | Livro-razão imutável com saldo materializado | Aceita |
| [ADR-10](#adr-10--idempotência-por-chave-uuid) | Idempotência por chave UUID | Aceita |
| [ADR-11](#adr-11--índices-unique-parciais-e-check-constraints-no-postgresql) | Índices UNIQUE parciais e CHECK constraints no PostgreSQL | Aceita |
| [ADR-12](#adr-12--jobs-por-empresa-com-shouldbeunique) | Jobs por empresa com `ShouldBeUnique` | Aceita |
| [ADR-13](#adr-13--auditoria-via-trait--json) | Auditoria via trait + JSON | Aceita |
| [ADR-14](#adr-14--anexos-em-disco-privado) | Anexos em disco privado | Aceita |
| [ADR-15](#adr-15--chartjs-para-gráficos) | Chart.js para gráficos | Aceita |
| [ADR-16](#adr-16--sessões-no-banco-de-dados) | Sessões no banco de dados | Aceita |
| [ADR-17](#adr-17--redis-para-cache-e-filas) | Redis para cache e filas | Aceita |
| [ADR-18](#adr-18--docker-apenas-para-infraestrutura-modo-híbrido-com-perfil-app-opcional) | Docker apenas para infraestrutura (modo híbrido), perfil `app` opcional | Aceita |
| [ADR-19](#adr-19--sem-download-de-fontes-no-build) | Sem download de fontes no build | Aceita |
| [ADR-20](#adr-20--preventlazyloading-fora-de-produção) | `preventLazyLoading` fora de produção | Aceita |

---

## ADR-01 — Monólito modular

**Contexto.** O ERP tem vários módulos (clientes, CRM, catálogo, propostas, contratos, operações, financeiro, relatórios) que compartilham entidades centrais (cliente, empresa, usuário) e precisam de **consistência transacional** entre si — converter uma proposta em contrato e gerar a primeira cobrança deve ser atômico. A equipe é pequena e o público inclui desenvolvedores iniciantes.

**Decisão.** Uma única aplicação Laravel, um único banco, organizada **por módulo dentro das pastas padrão do Laravel**: `app/Actions/{Proposals,Contracts,Finance,...}`, `app/Http/Controllers/{Commercial,Finance,Operations,...}`, `app/Livewire/{Crm,Finance,...}`, `resources/views/{proposals,contracts,...}`. A comunicação entre módulos acontece por chamadas diretas a Actions e por Events (ex.: `ProposalStatusChanged` → `MarkOpportunityAsWon`).

**Alternativas.**
- *Microsserviços:* isolamento e escala independentes, mas exigiriam transações distribuídas (sagas), filas entre serviços, vários deploys e observabilidade complexa — custo desproporcional para o tamanho do problema.
- *Pacotes/módulos separados (ex.: `nwidart/laravel-modules`, pasta `Modules/`):* fronteiras mais rígidas, porém estrutura diferente da documentação oficial, o que dificulta o aprendizado.

**Consequências.**
- ✅ Transações ACID simples (`DB::transaction`) atravessando módulos; um único deploy; refatorações com o apoio da IDE.
- ✅ Estrutura idêntica à documentação do Laravel: fácil para iniciantes.
- ⚠️ As fronteiras entre módulos dependem de disciplina (convenção de pastas, Actions como porta de entrada). Um módulo pode acessar Models de outro livremente.
- ⚠️ Escala vertical primeiro; partes pesadas (relatórios, rotinas diárias) já rodam em filas, o que alivia a requisição web.

---

## ADR-02 — Livewire class-based + Controllers (e não SPA)

**Contexto.** As telas do ERP são majoritariamente formulários e listagens com filtros. Algumas precisam de interatividade (busca em tempo real, Kanban do funil, dashboard). Manter um front-end SPA separado (React/Vue) dobraria a superfície de código, exigiria uma API para tudo e duplicaria validações.

**Decisão.**
- **Listagens** com busca/filtros/ordenação em tempo real e telas interativas → **componentes Livewire de página inteira**, no formato **class-based** (`config/livewire.php` → `'type' => 'class'`), com o trait [`WithDataTable`](../app/Livewire/Concerns/WithDataTable.php).
- **Formulários e telas de detalhe** → **Controllers + Form Requests + Blade** tradicionais.
- Interações puramente visuais → **Alpine.js** (já incluído no Livewire 4), com componentes registrados em [`resources/js/app.js`](../resources/js/app.js).

A convenção está escrita no topo de [`routes/web.php`](../routes/web.php).

**Alternativas.**
- *SPA (Inertia + Vue/React, ou SPA + API):* experiência rica, mas dois ecossistemas, build mais complexo e estado duplicado.
- *Livewire para tudo (inclusive formulários):* possível, mas formulários tradicionais com POST/redirect são mais simples de entender, testar e funcionam sem JavaScript.
- *Livewire Single File/Multi File Components (SFC/MFC):* mais compactos, mas o formato class-based separa PHP e Blade, combinando melhor com o restante do projeto e com a documentação clássica.

**Consequências.**
- ✅ Um só idioma (PHP/Blade); estado e autorização no servidor; filtros na URL (`#[Url]`) compartilháveis.
- ✅ A API REST existe para integrações, não é obrigatória para a interface.
- ⚠️ Cada interação Livewire é uma requisição HTTP: **toda ação precisa de `authorize()`** (não basta o `mount()`), e consultas pesadas precisam de eager loading.
- ⚠️ Interações muito ricas (offline, edição colaborativa) não são o forte do modelo.

---

## ADR-03 — Fortify com telas próprias

**Contexto.** O sistema precisa de login, recuperação de senha, verificação de e-mail, alteração de senha/perfil e, no futuro, 2FA. As telas precisam seguir o layout, o idioma e as regras do projeto (sem cadastro público; contas inativas bloqueadas; convite de usuários).

**Decisão.** Usar o **Laravel Fortify** como backend de autenticação (rotas, controllers, rate limit) e escrever **views Blade próprias** em `resources/views/auth`. Customizações em [`FortifyServiceProvider`](../app/Providers/FortifyServiceProvider.php): `authenticateUsing` (bloqueia contas inativas), rate limit de 5/min, e as Actions em `app/Actions/Fortify`. Registro público desativado.

**Alternativas.**
- *Breeze / starter kits:* geram telas e controllers que passam a ser código do projeto (mais código para manter) e com layout próprio.
- *Jetstream:* traz times, 2FA e mais, porém com conceitos ("teams") que não correspondem ao modelo multiempresa do ERP.
- *Autenticação escrita à mão:* risco de segurança e retrabalho.

**Consequências.**
- ✅ Fluxos de autenticação testados e mantidos pelo Laravel; o projeto controla 100% da interface.
- ✅ 2FA e passkeys podem ser ativados depois via `config/fortify.php` (veja o [roadmap](12-roadmap.md)).
- ⚠️ É preciso conhecer os "ganchos" do Fortify (`Fortify::loginView`, `authenticateUsing`, contratos de resposta) para customizar.

Detalhes em [07 — Autenticação e permissões](07-autenticacao-e-permissoes.md).

---

## ADR-04 — Permissões próprias por empresa (sem spatie/laravel-permission)

**Contexto.** Um usuário pode trabalhar em várias empresas com **perfis diferentes em cada uma**. Cada empresa deve poder customizar seus perfis. A permissão efetiva depende da **empresa ativa** na requisição.

**Decisão.** Modelo próprio e enxuto: `permissions` (catálogo global, sincronizado a partir de [`config/permissions.php`](../config/permissions.php) pelo `PermissionSeeder`), `roles` (por empresa), `permission_role` e o pivô `company_user` com **um** perfil por vínculo. A verificação é `User::hasPermission()` (uma consulta com JOINs, em cache na memória da requisição), integrada ao Laravel por `Gate::before` e pelas Policies. O superadministrador é a flag `users.is_super_admin`.

**Alternativas.**
- *spatie/laravel-permission:* excelente pacote, mas o recurso de "teams" exigiria configurar o `team_id` em toda requisição, além de trazer conceitos (permissões diretas no usuário, vários guards, cache global) que o projeto não precisa. Também esconderia do iniciante a lógica que é central no domínio.
- *Permissões no próprio usuário (sem perfis):* difícil de administrar em escala.

**Consequências.**
- ✅ Modelo explícito, pequeno (algumas dezenas de linhas) e alinhado ao domínio multiempresa.
- ✅ Uma consulta por requisição; mudanças de perfil valem na requisição seguinte.
- ⚠️ Funcionalidades extras (permissões diretas por usuário, cache compartilhado) precisariam ser escritas.
- ⚠️ Adicionar uma permissão nova **não** a distribui automaticamente aos perfis de empresas já existentes (só o administrador é ressincronizado por `ProvisionCompanyDefaults`).

---

## ADR-05 — Multiempresa por coluna `company_id` + Global Scope

**Contexto.** O sistema atende várias empresas (de um mesmo grupo ou clientes da plataforma) no mesmo servidor. Os dados não podem vazar entre elas, mas o superadministrador e o dashboard consolidado precisam, intencionalmente, consultar várias empresas.

**Decisão.** **Banco compartilhado, esquema compartilhado**: toda tabela de negócio tem `company_id`. O trait [`BelongsToCompany`](../app/Models/Concerns/BelongsToCompany.php) aplica o Global Scope [`CompanyScope`](../app/Models/Scopes/CompanyScope.php) (filtro automático), preenche `company_id` ao criar e impede mover registros entre empresas. A empresa ativa vive em [`CurrentCompany`](../app/Support/Tenancy/CurrentCompany.php) (instância *scoped* por requisição/job), definida pelo middleware `SetCurrentCompany` **antes** do Route Model Binding. Policies e regras `exists`/`unique` reforçam o isolamento.

**Alternativas.**
- *Banco (ou schema PostgreSQL) por empresa:* isolamento físico mais forte, mas migrations N vezes, conexões dinâmicas, backups e relatórios consolidados mais complexos, e um pacote de tenancy adicional.
- *Filtrar manualmente em cada consulta:* um único esquecimento vaza dados.

**Consequências.**
- ✅ Simples de operar (um banco, uma migration); consultas consolidadas possíveis com `forCompanies()`.
- ✅ Isolamento automático, inclusive na URL (ID de outra empresa → 404), coberto por testes.
- ⚠️ Toda consulta que **não** passa pelo Eloquent (Query Builder, `DB::table`, regras `exists`/`unique`) precisa filtrar `company_id` manualmente.
- ⚠️ `withoutGlobalScope` deve ser raro e justificado. Jobs e comandos precisam definir a empresa (`CurrentCompany::run()`).
- ⚠️ Índices compostos começando por `company_id` são necessários para desempenho.

---

## ADR-06 — Dinheiro em centavos inteiros + bcmath

**Contexto.** Números de ponto flutuante não representam exatamente valores decimais (`0.1 + 0.2 = 0.30000000000000004`). Em um ERP, um centavo de diferença quebra conciliações e gera desconfiança.

**Decisão.** Todo valor monetário é armazenado em colunas `bigint` com sufixo `_cents` e manipulado como `int` no PHP. A classe [`Money`](../app/Support/Money.php) converte texto digitado ("1.234,56") em centavos **sem float**, formata para exibição e multiplica preço × quantidade decimal com **bcmath** (`bcmul`) e arredondamento meio-para-cima. Quantidades são `decimal(12,3)`. A API devolve `{"cents": ..., "formatted": ...}`.

**Alternativas.**
- *`decimal(15,2)` no banco:* exato no banco, mas o PHP o lê como string e a tentação de converter para float é grande.
- *Biblioteca `moneyphp/money` / `brick/money`:* robustas, porém mais conceitos e uma dependência a mais para um sistema de moeda única.

**Consequências.**
- ✅ Somas e comparações exatas; o teste "0,10 + 0,20 = 0,30" prova isso.
- ✅ Contrato claro na API (sempre centavos).
- ⚠️ É preciso lembrar de converter na entrada (traits `NormalizesMoney`/`NormalizesLineItems`) e na saída (`money()`).
- ⚠️ Multi-moeda exigiria evoluir o modelo (veja o [roadmap](12-roadmap.md)).

---

## ADR-07 — Actions em vez de Repositories

**Contexto.** As regras de negócio (registrar pagamento, converter proposta, mudar status de contrato) precisam ser reutilizadas pela web, pela API, por jobs e pelos testes. Colocá-las nos Controllers duplicaria código; colocá-las nos Models criaria "Models gordos".

**Decisão.** Cada operação de negócio é uma classe **Action** em `app/Actions/<Módulo>/`, com um método público (`handle()` ou nomes como `create()`/`update()`/`forCurrentCompany()`), dependências injetadas pelo construtor e responsabilidade completa pela operação: transação, locks, validação de regras (`BusinessRuleException`), auditoria e disparo de Events. Lógica reutilizável sem estado fica em **Services** (`LedgerService`, `DocumentNumberGenerator`, `LineItemCalculator`). O acesso a dados usa o **Eloquent diretamente**.

**Alternativas.**
- *Repository pattern:* uma camada sobre o Eloquent para "abstrair o banco". No Laravel, geralmente vira um repasse de métodos (`find`, `create`), esconde recursos do Eloquent (scopes, eager loading, locks) e não resolve onde a regra de negócio mora.
- *Services grandes por módulo (`FinanceService` com 20 métodos):* tendem a crescer sem limite.
- *Regras no Controller:* duplicação entre web e API.

**Consequências.**
- ✅ Uma regra, um lugar; fácil de testar sem HTTP (`app(RecordPayment::class)->handle(...)`).
- ✅ Nomes que descrevem intenção (`ReversePayment`, `ConvertProposalToContract`).
- ⚠️ Cadastros simples sem regra continuam no Controller (ex.: `CatalogItemController`): é preciso bom senso para decidir quando criar uma Action.
- ⚠️ Sem Repository, trocar de ORM seria trabalhoso — um risco aceito, pois não é um objetivo.

---

## ADR-08 — Status como Enum com máquina de estados

**Contexto.** Propostas, contratos, ordens de serviço e chamados têm ciclos de vida com transições permitidas e proibidas (um contrato cancelado não pode ser reativado). Regras espalhadas em `if`s ficam inconsistentes.

**Decisão.** Cada status é um **Enum PHP** (`app/Enums/*Status.php`) com `label()`, `color()` e `transitions()`. O trait [`HasStateMachine`](../app/Enums/Concerns/HasStateMachine.php) oferece `canTransitionTo()` e `isFinal()`. As Actions de mudança de status verificam a transição dentro de uma transação com `lockForUpdate` e registram o histórico (`HasStatusHistory` → tabela `status_changes`). No banco, o status é uma `string`. "Vencido" **não** é status armazenado: é derivado (vencimento + status em aberto).

**Alternativas.**
- *Tabela de status no banco:* flexível para o usuário, mas as regras dependem do código de qualquer forma.
- *Pacote de state machine (ex.: spatie/laravel-model-states):* poderoso, porém mais abstrações para casos simples.
- *Status "vencido" armazenado:* exigiria um job para atualizar diariamente e poderia ficar inconsistente.

**Consequências.**
- ✅ Regras de transição num único lugar, visíveis e testáveis; a interface gera os botões a partir de `transitions()`.
- ✅ Cast do Eloquent impede valores inválidos.
- ⚠️ Adicionar um status exige deploy (é uma mudança de regra, então faz sentido).

---

## ADR-09 — Livro-razão imutável com saldo materializado

**Contexto.** O saldo das contas financeiras precisa ser **exato, auditável e rápido** de consultar. Pagamentos podem ser estornados; lançamentos não podem "sumir".

**Decisão.** Toda movimentação passa pelo [`LedgerService`](../app/Services/Finance/LedgerService.php), que (dentro de uma transação) trava a conta com `lockForUpdate`, valida o saldo, grava um lançamento em `financial_transactions` com `balance_after_cents` e atualiza o **saldo materializado** `financial_accounts.current_balance_cents`. Lançamentos são **imutáveis**: o Model [`FinancialTransaction`](../app/Models/FinancialTransaction.php) lança exceção em `updating`/`deleting`; correções são feitas por **estorno** (um lançamento inverso com `reversal_of_id`). Títulos financeiros são cancelados, nunca excluídos. O comando `finance:verify-balances` confere diariamente saldo × extrato.

**Alternativas.**
- *Calcular o saldo somando os lançamentos a cada consulta:* sempre correto, mas lento com o crescimento do histórico.
- *Só o saldo, sem extrato:* rápido, mas sem rastreabilidade.
- *Editar/apagar lançamentos errados:* simples, mas destrói a trilha contábil.

**Consequências.**
- ✅ Saldo instantâneo **e** histórico completo; qualquer divergência é detectada pelo comando de conferência.
- ✅ Concorrência segura: duas operações simultâneas nunca leem o mesmo saldo.
- ⚠️ O `LedgerService` deve ser o **único** caminho para mexer em saldos (alterações diretas no banco são detectadas como divergência).
- ⚠️ O extrato cresce indefinidamente (aceitável; arquivamento é tema futuro).

---

## ADR-10 — Idempotência por chave UUID

**Contexto.** Duplo clique, reenvio do navegador, retry de integrações após timeout: a mesma intenção de pagamento pode chegar duas vezes. Gerar dois pagamentos é inaceitável.

**Decisão.** Cada formulário/requisição de pagamento ou transferência carrega uma `idempotency_key` (UUID) gerada pelo cliente. As tabelas `payments` e `transfers` têm índice **UNIQUE `(company_id, idempotency_key)`**. A Action ([`RecordPayment`](../app/Actions/Finance/RecordPayment.php), `CreateTransfer`) procura a chave antes de processar, trata `UniqueConstraintViolationException` (corrida entre requisições simultâneas) devolvendo o registro vencedor e recusa a reutilização da chave para outro título. A API responde 201 na criação e 200 no retry.

**Alternativas.**
- *Desabilitar o botão no front-end:* ajuda, mas não protege contra retries de rede nem chamadas de API.
- *Detectar duplicidade por "mesmo valor, mesmo dia":* falsos positivos (dois pagamentos legítimos iguais).
- *Cache/lock no Redis por chave:* expira e não é transacional com o banco.

**Consequências.**
- ✅ Garantia no nível do banco, inclusive sob concorrência real.
- ✅ Integrações podem fazer retry com segurança.
- ⚠️ O cliente precisa gerar e reutilizar a chave corretamente (documentado em [13 — API](13-api.md#8-idempotência-nos-pagamentos)).

---

## ADR-11 — Índices UNIQUE parciais e CHECK constraints no PostgreSQL

**Contexto.** Regras de integridade validadas só no PHP podem ser burladas por concorrência, por bugs ou por alterações diretas no banco. Ao mesmo tempo, registros com soft delete não devem bloquear a reutilização de valores únicos (CPF/CNPJ, códigos).

**Decisão.** Usar recursos do **PostgreSQL** como última barreira, via `DB::statement` nas migrations:
- UNIQUE **parcial**: `(company_id, document) WHERE deleted_at IS NULL` em clientes/fornecedores; `(company_id, code) WHERE deleted_at IS NULL` em catálogo e unidades; `(contract_id, competence) WHERE contract_id IS NOT NULL` em contas a receber (uma cobrança por competência); `(recurring_expense_id, competence)` em contas a pagar.
- CHECK: `paid_cents <= amount_cents`, `amount_cents > 0`, `billing_day BETWEEN 1 AND 28`, `end_date >= start_date`, pagamento ligado a exatamente um título, transferência entre contas diferentes etc.

Os Form Requests espelham essas regras para mostrar mensagens amigáveis; o banco garante a integridade.

**Alternativas.**
- *Somente validação na aplicação:* sujeita a condições de corrida.
- *Banco agnóstico (MySQL/SQLite):* MySQL não tem índice parcial nativo; SQLite em memória nos testes esconderia exatamente esses comportamentos.

**Consequências.**
- ✅ Integridade garantida mesmo com bugs ou concorrência; testes provam que o banco rejeita duplicidades.
- ⚠️ O projeto fica acoplado ao PostgreSQL (aceito), e os testes rodam em PostgreSQL real (`nexus_erp_testing`).
- ⚠️ Ao criar uma migration nova, lembre-se de que o Schema Builder não cria índices parciais: use `DB::statement`.

---

## ADR-12 — Jobs por empresa com `ShouldBeUnique`

**Contexto.** Rotinas diárias (cobranças recorrentes, despesas recorrentes, expiração de propostas, vigência de contratos, avisos de inadimplência) precisam rodar para todas as empresas, sem que a falha de uma afete as outras e sem execuções duplicadas.

**Decisão.** O Scheduler ([`routes/console.php`](../routes/console.php)) executa `erp:daily-routines` às 06:00 (`withoutOverlapping`, `onOneServer`). O comando ([`RunDailyRoutines`](../app/Console/Commands/RunDailyRoutines.php)) enfileira **um job por empresa e por rotina**. Os jobs implementam `ShouldBeUnique` com `uniqueId()` = id da empresa (trait [`RunsForCompany`](../app/Jobs/Concerns/RunsForCompany.php)), recebem a `Company` no construtor e executam a Action dentro de `CurrentCompany::run()`. As Actions são idempotentes (o índice UNIQUE por competência impede cobranças duplicadas). Opções `--sync` e `--company=ID` facilitam testes e reprocessamentos.

**Alternativas.**
- *Um único job que percorre todas as empresas:* uma falha interrompe as demais; tempo de execução cresce linearmente.
- *Rodar tudo de forma síncrona no comando:* sem retries, sem paralelismo.

**Consequências.**
- ✅ Isolamento de falhas, retries (`tries`/`backoff`) e paralelismo com vários workers.
- ✅ O CompanyScope funciona no job exatamente como na web.
- ⚠️ Depende de um worker de filas rodando (`php artisan queue:work`) e do cron do Scheduler em produção.
- ⚠️ `ShouldBeUnique` usa o cache (Redis) para o lock: o cache precisa estar disponível.

---

## ADR-13 — Auditoria via trait + JSON

**Contexto.** Um ERP precisa responder "quem alterou o quê, quando e de onde", sem que cada desenvolvedor lembre de registrar manualmente cada alteração.

**Decisão.** O trait [`Auditable`](../app/Models/Concerns/Auditable.php) usa os eventos do Eloquent (`created`, `updated`, `deleted`, `restored`) para gravar automaticamente em `audit_logs`, com valores antigos e novos em colunas **`jsonb`**. Além disso, as Actions registram eventos de **intenção** (`payment.recorded`, `proposal.approved`) via [`AuditLogger`](../app/Services/AuditLogger.php). Campos sensíveis (`password`, tokens, segredos) nunca são gravados. A tela de auditoria filtra por empresa.

**Alternativas.**
- *Pacotes (owen-it/laravel-auditing, spatie/laravel-activitylog):* completos, mas mais configuração e conceitos.
- *Uma tabela de histórico por entidade:* integridade forte, porém dezenas de tabelas.
- *Triggers no banco:* não conhecem o usuário/empresa da aplicação.

**Consequências.**
- ✅ Auditoria "de graça" para todo Model com o trait; JSON guarda uma fotografia flexível dos valores.
- ✅ `AuditLogger::withoutAuditing()` permite cargas em massa (seeders) sem poluir o histórico.
- ⚠️ Alterações feitas **sem** eventos do Eloquent (`Query Builder`, `update()` em massa, `saveQuietly()`) não são auditadas automaticamente.
- ⚠️ O `AuditLogger` captura exceções para "nunca derrubar a operação principal"; mas, no PostgreSQL, um erro de SQL dentro de uma transação aberta invalida a transação inteira — então essa proteção vale plenamente apenas fora de transações.
- ⚠️ A tabela cresce rápido; índices por `(company_id, created_at)` e uma política de retenção futura são necessários.

---

## ADR-14 — Anexos em disco privado

**Contexto.** Anexos (contratos assinados, comprovantes, documentos de clientes) contêm dados sensíveis e pertencem a uma empresa específica.

**Decisão.** Arquivos são gravados no disco `local` (`storage/app/private`), **fora** da pasta pública, em `attachments/{company_id}/{tipo}/` com **nome aleatório** (UUID) — o nome original fica só no banco ([`StoreAttachment`](../app/Actions/Attachments/StoreAttachment.php)). O download passa pelo [`AttachmentController`](../app/Http/Controllers/AttachmentController.php), que autoriza via [`AttachmentPolicy`](../app/Policies/AttachmentPolicy.php) (delegando à Policy do registro "dono"). O upload aceita apenas extensões permitidas (até 10 MB) e só tipos de registro do morph map.

**Alternativas.**
- *Disco `public` com link simbólico:* qualquer pessoa com a URL baixaria o arquivo.
- *URLs temporárias assinadas:* boa opção com S3; pode ser adotada no futuro mantendo a autorização.

**Consequências.**
- ✅ Nenhum arquivo é acessível sem login, permissão e empresa correta (testado em `SecurityTest`).
- ✅ Nomes aleatórios evitam colisões e vazamento de informação pelo nome do arquivo.
- ⚠️ Downloads passam pelo PHP (mais carga que servir arquivo estático); migrar para S3/compatível é simples via configuração de disco.

---

## ADR-15 — Chart.js para gráficos

**Contexto.** O dashboard e o fluxo de caixa precisam de gráficos de barras, linhas e rosca, renderizados a partir de dados do servidor, com tema claro/escuro.

**Decisão.** **Chart.js** (dependência npm), exposto como `window.Chart` em [`resources/js/app.js`](../resources/js/app.js) e encapsulado no componente Blade [`components/chart.blade.php`](../resources/views/components/chart.blade.php). Os dados são calculados no servidor ([`DashboardMetrics`](../app/Services/Dashboard/DashboardMetrics.php)) e entregues como arrays (`labels`, séries).

**Alternativas.**
- *ApexCharts / ECharts:* mais recursos, bundles maiores.
- *Gráficos em SVG gerados no servidor:* sem interatividade.
- *Bibliotecas ligadas a React/Vue:* incompatíveis com a decisão do ADR-02.

**Consequências.**
- ✅ Biblioteca popular, leve, bem documentada e sem framework.
- ⚠️ É preciso recriar/atualizar o gráfico quando o Livewire re-renderiza o componente (tratado no componente Blade).

---

## ADR-16 — Sessões no banco de dados

**Contexto.** O perfil do usuário precisa listar sessões ativas (dispositivo, IP, última atividade) e permitir encerrar sessões de outros dispositivos; ao desativar um usuário, suas sessões precisam cair imediatamente.

**Decisão.** `SESSION_DRIVER=database` (tabela `sessions`), com `SESSION_ENCRYPT=true`. O [`ProfileController`](../app/Http/Controllers/ProfileController.php) lista e apaga sessões; [`ToggleUserAccess`](../app/Actions/Users/ToggleUserAccess.php) remove as sessões do usuário desativado.

**Alternativas.**
- *Sessões em arquivo ou cookie:* não permitem listar nem revogar sessões de um usuário.
- *Sessões no Redis:* rápidas, mas listar por usuário exigiria estruturas extras.

**Consequências.**
- ✅ Visibilidade e controle das sessões; revogação imediata.
- ⚠️ Uma escrita no banco por requisição (atualização de `last_activity`) — aceitável para o volume de um ERP; pode ser revisto se virar gargalo.

---

## ADR-17 — Redis para cache e filas

**Contexto.** Filas (notificações, rotinas diárias), locks de jobs únicos e cache precisam de um backend rápido e confiável.

**Decisão.** `QUEUE_CONNECTION=redis` e `CACHE_STORE=redis` (cliente `predis`), com Redis 7 no Docker. Nos testes, `QUEUE_CONNECTION=sync` e `CACHE_STORE=array` (definidos no `phpunit.xml`).

**Alternativas.**
- *Fila e cache no banco (`database`):* sem serviço extra, mas aumentam a carga do PostgreSQL e o polling da fila.
- *SQS/serviços gerenciados:* ótimos em produção, mas acoplam o desenvolvimento local à nuvem.

**Consequências.**
- ✅ Filas rápidas; base pronta para Laravel Horizon (veja o [roadmap](12-roadmap.md)).
- ⚠️ Mais um serviço para operar e monitorar; se o Redis cair, filas, cache e locks de `ShouldBeUnique` param.

---

## ADR-18 — Docker apenas para infraestrutura (modo híbrido), com perfil `app` opcional

**Contexto.** Desenvolvedores (especialmente iniciantes, muitos no Windows) precisam de PostgreSQL 17, Redis e um servidor de e-mail de teste sem instalar tudo na máquina; ao mesmo tempo, rodar PHP dentro de containers no Windows costuma ser lento (volumes) e dificulta o uso de ferramentas locais.

**Decisão.** O [`docker-compose.yml`](../docker-compose.yml) sobe por padrão **somente a infraestrutura**: `pgsql` (porta 5433 para não conflitar com um PostgreSQL local), `redis` e `mailpit`. O PHP roda localmente (`php artisan serve`, `composer run dev`). Um **perfil `app`** opcional (`docker compose --profile app up -d --build`) roda aplicação, worker de filas e scheduler em containers ([`docker/php/Dockerfile`](../docker/php/Dockerfile)). O container do PostgreSQL cria automaticamente o banco de testes `nexus_erp_testing`.

**Alternativas.**
- *Tudo em Docker (Sail):* ambiente idêntico para todos, mas mais lento em alguns sistemas e com mais camadas para o iniciante entender.
- *Tudo instalado localmente:* sem Docker, porém instalação e versões variam entre máquinas.

**Consequências.**
- ✅ Setup rápido, desempenho nativo do PHP, mesmas versões de banco e Redis para todos.
- ✅ Quem preferir pode rodar tudo em containers com o perfil `app`.
- ⚠️ É necessário ter PHP 8.4 (com `pdo_pgsql`, `bcmath`, `intl`) instalado localmente no modo híbrido.

---

## ADR-19 — Sem download de fontes no build

**Contexto.** O plugin do Laravel para Vite pode baixar fontes de um serviço externo durante o build. Isso faz o build depender de internet e envia requisições a terceiros.

**Decisão.** Não usar o download automático de fontes (comentário em [`vite.config.js`](../vite.config.js)). A pilha de fontes em [`resources/css/app.css`](../resources/css/app.css) usa `Inter` quando instalada e, na falta dela, as fontes do sistema (Segoe UI, Roboto, Helvetica...). Nenhuma fonte é carregada de CDN no `<head>`.

**Alternativas.**
- *Google Fonts/Bunny Fonts via CDN:* visual idêntico em todas as máquinas, mas dependência externa e questões de privacidade (LGPD).
- *Hospedar os arquivos da fonte no projeto:* viável no futuro, ao custo de alguns KB.

**Consequências.**
- ✅ Build offline e reprodutível; nenhuma requisição a terceiros; carregamento mais rápido.
- ⚠️ A tipografia pode variar levemente entre sistemas operacionais.

---

## ADR-20 — `preventLazyLoading` fora de produção

**Contexto.** O problema de **N+1 consultas** (carregar uma relação dentro de um loop, gerando uma consulta por linha) é silencioso: a tela funciona, mas fica lenta à medida que os dados crescem.

**Decisão.** No [`AppServiceProvider`](../app/Providers/AppServiceProvider.php): `Model::preventLazyLoading(! $this->app->isProduction())` e `Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction())`. Em desenvolvimento e testes, acessar uma relação não carregada lança exceção; atribuir um campo fora do `#[Fillable]` também. Em produção, os dois ficam desligados para não derrubar o usuário por causa de um detalhe de desempenho.

**Alternativas.**
- *Laravel Debugbar/Telescope para detectar N+1:* úteis, mas dependem de alguém olhar.
- *Ativar também em produção:* mais rígido, porém um N+1 esquecido viraria erro 500 para o usuário.

**Consequências.**
- ✅ N+1 é detectado cedo — o teste de fumaça (`SmokeTest`) abre todas as telas e falha se houver lazy loading.
- ✅ Atributos digitados errado não são ignorados em silêncio.
- ⚠️ Exige eager loading explícito (`with()`/`load()`) em Controllers e componentes — o que é exatamente o objetivo.

---

## Como registrar uma nova decisão

1. Copie o formato acima (Contexto, Decisão, Alternativas, Consequências).
2. Dê o próximo número (ADR-21...) e acrescente-o ao índice.
3. Se a decisão substituir outra, marque a antiga como "Substituída por ADR-XX" em vez de apagá-la.
4. Cite os arquivos do código afetados, para que o leitor possa conferir.

Referências: [Architecture Decision Records (adr.github.io)](https://adr.github.io/) · [Laravel: Service Container](https://laravel.com/docs/13.x/container) · [Eloquent: Global Scopes](https://laravel.com/docs/13.x/eloquent#global-scopes) · [Queues: Unique Jobs](https://laravel.com/docs/13.x/queues#unique-jobs) · [Livewire](https://livewire.laravel.com/docs/quickstart).
