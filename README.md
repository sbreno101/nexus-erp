# Nexus ERP

Plataforma de gestão empresarial para **empresas prestadoras de serviços**, construída com Laravel 13, Livewire 4, Tailwind CSS 4 e PostgreSQL — como um projeto de estudo realista, com código profissional e documentação didática.

> **Aplicação executável ≠ aplicação homologada para produção.** Veja a seção [Antes de usar com dados reais](#antes-de-usar-com-dados-reais).

## Módulos

| Área | O que faz |
|------|-----------|
| Empresas e unidades | Multiempresa (vários CNPJs) com isolamento total de dados, unidades/filiais, configurações por empresa, logotipo |
| Usuários e permissões | Login, recuperação de senha, verificação de e-mail, perfil, sessões ativas, convites, perfis por empresa com permissões granulares |
| Clientes e fornecedores | PF/PJ com validação de CPF/CNPJ (inclusive CNPJ alfanumérico), duplicidade por empresa, endereços, contatos, histórico, anexos, CSV |
| CRM | Leads, origens, funil configurável, oportunidades em tabela e Kanban (arrastar e soltar), interações, tarefas, conversão em cliente |
| Catálogo | Produtos e serviços, categorias, preços com histórico de alterações |
| Propostas | Itens do catálogo ou livres, descontos, totais calculados no servidor, numeração, PDF, ciclo de status, conversão em contrato |
| Contratos | Vigência, periodicidade, ativação/suspensão/cancelamento/encerramento, renovação, cobranças recorrentes automáticas |
| Ordens de serviço | Prioridade, responsável, prazo, atividades com tempo gasto, conclusão com solução |
| Chamados | Conversa com o cliente, comentários internos, categorias, tempos de resposta/resolução |
| Financeiro | Contas a receber/pagar, baixas parciais, estornos, contas financeiras com extrato imutável, transferências, despesas recorrentes, fluxo de caixa previsto x realizado |
| Dashboard | Indicadores reais e gráficos, filtro por período e por empresa (ou consolidado) |
| Relatórios | 9 relatórios com filtros e exportação CSV/PDF |
| Auditoria e API | Trilha de auditoria consultável e API REST `/api/v1` com Sanctum |

## Requisitos

- PHP **8.3+** com extensões `pdo_pgsql`, `bcmath`, `mbstring`, `fileinfo`, `gd`
- Composer 2
- Node.js 20+ e npm
- Docker Desktop (para PostgreSQL, Redis e Mailpit) — ou PostgreSQL 15+ e Redis instalados localmente

## Instalação (passo a passo)

```bash
# 1. Subir a infraestrutura (PostgreSQL na porta 5433, Redis 6379, Mailpit 1025/8025)
docker compose up -d

# 2. Instalar dependências, criar .env, gerar chave, migrar, popular dados demo e compilar assets
composer setup

# 3. Iniciar servidor web + worker da fila + Vite
composer dev
```

Acesse **http://localhost:8000**. Os e-mails enviados pelo sistema (convites, recuperação de senha, avisos) aparecem no Mailpit: **http://localhost:8025**.

> O `composer setup` não apaga dados: se o banco já tiver os dados demonstrativos, o seeder apenas avisa. Para recriar tudo do zero (**apaga o banco de desenvolvimento**): `php artisan migrate:fresh --seed`.

### Tarefas agendadas e filas

Em desenvolvimento, deixe também em um terminal:

```bash
php artisan schedule:work
```

O Scheduler executa diariamente `erp:daily-routines` (cobranças recorrentes de contratos, despesas recorrentes, expiração de propostas, vigência de contratos e aviso de títulos vencidos) e `finance:verify-balances` (confere saldos x extratos). Para rodar na hora, sem esperar o agendamento:

```bash
php artisan erp:daily-routines --sync
```

Em produção: um cron `* * * * * php artisan schedule:run` e um supervisor mantendo `php artisan queue:work` ativo.

### Modo 100% Docker (opcional)

```bash
docker compose --profile app up -d --build
```

Sobe também a aplicação, o worker e o scheduler em containers (imagem em `docker/php/Dockerfile`). Ajuste o `.env` se necessário; os hosts internos (`pgsql`, `redis`, `mailpit`) são definidos no próprio compose.

## Acesso de demonstração (somente ambiente local)

Todos os usuários demo usam a senha **`Nexus@2026`**. Estas credenciais existem apenas no seeder de desenvolvimento (que não roda em produção) e **nunca devem ser usadas em um servidor real**.

| E-mail | Perfil |
|--------|--------|
| `admin@nexuserp.local` | Superadministrador (todas as empresas) |
| `admin@verticeti.local` | Administrador da empresa Vértice TI |
| `gerente@verticeti.local` | Gerente (também tem acesso à Órbita Facilities) |
| `comercial@verticeti.local` | Comercial |
| `financeiro@verticeti.local` | Financeiro |
| `atendimento@verticeti.local` | Atendimento |
| `colaborador@verticeti.local` | Colaborador |
| `admin@orbita.local` | Administrador da empresa Órbita Facilities |

## Comandos úteis

| Comando | Para quê |
|---------|----------|
| `composer test` | Suíte de testes (Pest) no banco isolado `nexus_erp_testing` |
| `composer lint` | Formatação com Laravel Pint |
| `composer analyse` | Análise estática com Larastan |
| `composer check` | Pint + Larastan + testes |
| `npm run build` | Compila CSS/JS para produção |
| `php artisan finance:verify-balances` | Confere o saldo de cada conta contra o extrato |
| `php artisan db:seed --class=PermissionSeeder` | Sincroniza permissões após editar `config/permissions.php` |

## API

Documentação completa em [docs/13-api.md](docs/13-api.md). Exemplo rápido:

```bash
curl -X POST http://localhost:8000/api/v1/auth/token -H "Accept: application/json" -d "email=admin@verticeti.local" -d "password=Nexus@2026" -d "device_name=curl"
```

## Documentação didática

| # | Documento |
|---|-----------|
| 01 | [Visão geral](docs/01-visao-geral.md) |
| 02 | [Arquitetura](docs/02-arquitetura.md) |
| 03 | [Banco de dados (ER + dicionário)](docs/03-banco-de-dados.md) |
| 04 | [Fluxo de uma requisição](docs/04-fluxo-de-uma-requisicao.md) |
| 05 | [Eloquent ORM](docs/05-eloquent-orm.md) |
| 06 | [Regras de negócio](docs/06-regras-de-negocio.md) |
| 07 | [Autenticação e permissões](docs/07-autenticacao-e-permissoes.md) |
| 08 | [Testes](docs/08-testes.md) |
| 09 | [Como adicionar uma funcionalidade](docs/09-como-adicionar-uma-funcionalidade.md) |
| 10 | [Guia de estudos](docs/10-guia-de-estudos.md) |
| 11 | [Decisões de arquitetura](docs/11-decisoes-de-arquitetura.md) |
| 12 | [Roadmap](docs/12-roadmap.md) |
| 13 | [API REST](docs/13-api.md) |

O andamento do projeto, problemas encontrados e pendências estão em [PROGRESS.md](PROGRESS.md).

## Stack

Laravel 13 · PHP 8.4 · Livewire 4 (+ Alpine.js) · Tailwind CSS 4 · Vite · PostgreSQL 17 · Redis 7 · Laravel Fortify · Laravel Sanctum · Pest 5 · Larastan · Laravel Pint · Chart.js · Dompdf · Docker Compose · Mailpit

## Limitações conhecidas

- **Sem integrações externas**: não há emissão fiscal (NFS-e), boletos/PIX reais, conciliação bancária automática nem processamento de cartões. Pagamentos são **registrados manualmente**.
- Sem portal do cliente: chamados e mensagens são registrados pela equipe interna.
- Sem autenticação em dois fatores (o Fortify suporta; veja o roadmap).
- Sem juros/multa automáticos por atraso e sem reajuste automático de contratos por índice.
- Fonte da interface: usa a fonte do sistema (o download de fontes no build foi removido para funcionar offline).
- Análise estática configurada no nível 1 do PHPStan (erros reais); elevar o nível exige anotar tipos dos Models (ver roadmap).

## Antes de usar com dados reais

Esta é uma aplicação **executável e testada**, mas **não homologada para produção**. Antes de operar com dados reais:

1. Revisão de segurança independente (pentest), principalmente autorização multiempresa e upload de arquivos.
2. `APP_ENV=production`, `APP_DEBUG=false`, HTTPS obrigatório, `SESSION_SECURE_COOKIE=true`, credenciais fortes e únicas (nunca as do seeder).
3. Backups automáticos e testados do PostgreSQL e do diretório `storage/app/private` (anexos); política de retenção.
4. Validação contábil/fiscal das regras financeiras com um contador; adequação à LGPD (bases legais, retenção, direito de exclusão).
5. Monitoramento (logs centralizados, alertas de `finance:verify-balances`, fila parada, erros), worker de fila supervisionado e cron do Scheduler.
6. Teste de carga e índices revisados para o volume esperado; política de senhas mais forte (já ativa em produção via `Password::defaults`).

## Licença

Projeto de estudo. Use, modifique e aprenda à vontade.
