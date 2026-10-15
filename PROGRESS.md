# PROGRESS — Nexus ERP

Registro de andamento do projeto (para retomar o trabalho sem recomeçar).

## Ambiente verificado
- PHP 8.4.20 (pdo_pgsql, bcmath), Composer 2.10, Node 24, Docker 29.
- Laravel 13.36, Livewire 4.4 (componentes em classe), Fortify 1.41, Sanctum 4.3, Pest 5.3, Larastan 3, Pint, Tailwind 4, Chart.js 4, Dompdf 3.
- Infra via `docker compose up -d`: PostgreSQL 17 na porta **5433** (a 5432 é usada por um PostgreSQL local já instalado), Redis 7, Mailpit (SMTP 1025, painel 8025).
- Modo 100% Docker (`--profile app`): imagem construída e verificada (extensões pdo_pgsql/bcmath/intl/gd; acesso ao PostgreSQL e ao Redis de dentro do container).

## Concluído
- [x] **Fase 1 — Fundação**: projeto, Docker Compose, `.env`/`.env.example`, Fortify (login, logout, recuperação de senha, verificação de e-mail, perfil, senha, limite de tentativas), layout administrativo (sidebar, cabeçalho, seletor de empresa, tema claro/escuro, notificações, toasts), componentes Blade, traduções pt-BR.
- [x] **Fase 2 — Identidade e empresas**: multiempresa (CompanyScope + CurrentCompany + middleware antes do Route Model Binding), perfis por empresa com permissões granulares (`config/permissions.php`), Policies, usuários com convite por e-mail, ativação/desativação (vínculo e conta), sessões ativas, unidades, superadministrador, cadastro de empresas.
- [x] **Fase 3 — Comercial**: clientes/fornecedores (CPF/CNPJ inclusive alfanumérico, duplicidade por empresa, endereços, contatos, histórico, anexos, CSV), CRM (leads, conversão, oportunidades, Kanban persistido, etapas e origens configuráveis, interações, tarefas), catálogo (categorias, histórico de preços), propostas (itens, totais no servidor, PDF, ciclo de status), contratos (status, renovação, cobranças).
- [x] **Fase 4 — Operações**: ordens de serviço (atividades, status, conclusão com solução), chamados (mensagens, comentários internos, categorias), anexos privados com download autorizado, histórico de status e auditoria.
- [x] **Fase 5 — Financeiro**: contas financeiras com livro-razão imutável e saldo conferível, contas a receber/pagar, baixas parciais com idempotência e lock, estornos, cancelamentos, transferências (lock ordenado), lançamentos avulsos, despesas recorrentes, cobranças recorrentes de contratos, fluxo de caixa previsto x realizado, categorias e centros de custo.
- [x] **Fase 6 — Gestão**: dashboard (KPIs reais, 5 gráficos, filtro de período e empresa/consolidado), 9 relatórios com filtros e exportação CSV/PDF.
- [x] **Fase 7 — API e segurança**: API REST `/api/v1` (Sanctum, Resources, paginação, filtros, ordenação, erros padronizados em pt-BR, rate limit, idempotência), auditoria consultável, cabeçalhos de segurança, proteção de arquivos, revisão de autorização.
- [x] **Fase 8 — Qualidade**: 169 testes Pest passando (605 asserções), Pint aplicado, Larastan nível 1 sem erros, seeder demonstrativo idempotente, README, documentação em `docs/`.
- [x] **Revisão pós-documentação**: correções com testes: renovação retomando a cobrança recorrente, rateio do desconto geral da proposta nos itens do contrato, validação de cobrança duplicada por competência, estornos de transferência fora do fluxo de caixa, bloqueio de atividades em OS finalizada, desativação por empresa sem derrubar acessos às outras empresas, auditoria com SAVEPOINT, taxa de conversão pela data da decisão, 429 da API em português, `PermissionSeeder` sincronizando o perfil administrador das empresas existentes.
- [x] Scheduler (`erp:daily-routines`, `finance:verify-balances`, limpeza de tokens) + Jobs por empresa (ShouldBeUnique).

## Pendências / próximos passos sugeridos
- [ ] Subir o nível do Larastan anotando os tipos (`@property`) de todos os Models (hoje: nível 1; os níveis maiores geram falsos positivos porque algumas tabelas são criadas em loop nas migrations).
- [ ] Itens do roadmap (`docs/12-roadmap.md`): integrações bancárias/fiscais, 2FA, portal do cliente, juros/multa, reajuste por índice, CI/CD.

## Comandos de verificação
```bash
docker compose up -d
composer setup            # ou: php artisan migrate:fresh --seed (apaga o banco de dev)
composer test             # 169 testes no banco nexus_erp_testing
composer analyse          # Larastan
composer lint             # Pint
php artisan finance:verify-balances
php artisan erp:daily-routines --sync
npm run build
```

## Problemas encontrados e soluções
- Docker Desktop não iniciava (Hyper-V/socket preso) → resolvido pelo usuário com reinício do Windows.
- PostgreSQL local ocupando a porta 5432 → container publicado na 5433.
- Download de fontes no build (fonts.bunny.net) sem rede → removido; fonte do sistema.
- `@js()` não é compilado dentro de atributos de componentes Blade → usar `'{{ }}'`.
- `@use` dentro de slot de componente gera ParseError → declarar no topo da view.
- `wire:loading.delay.flex` (modificadores combinados) não é ocultado pelo CSS do Livewire → wrapper com `wire:loading.delay`.
- PostgreSQL não tem `MAX(boolean)` → `primaryAddress` usa filtro `is_primary = true`.
- Acesso preguiçoso a `settings` com usuário em várias empresas (pego pelo `preventLazyLoading`) → `settingsOrDefault()`.
- Índices UNIQUE de código (catálogo/unidades) passaram a ser parciais (`WHERE deleted_at IS NULL`) em nova migration.
- CSV: números negativos recebiam apóstrofo (proteção contra fórmulas) → exceção para números.
- Laravel 13 renomeou o middleware de CSRF para `PreventRequestForgery`.
- Erro de INSERT da auditoria dentro de uma transação abortava a transação inteira no PostgreSQL → `AuditLogger` grava em transação aninhada (SAVEPOINT).
