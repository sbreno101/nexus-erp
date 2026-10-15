<?php

/*
|--------------------------------------------------------------------------
| Catálogo de permissões e perfis padrão
|--------------------------------------------------------------------------
|
| "groups": todas as permissões do sistema, no formato "recurso.ação",
| agrupadas por módulo (o agrupamento é usado na tela de perfis).
|
| "roles": perfis criados automaticamente para cada nova empresa
| (Action CreateCompany). O valor "*" significa "todas as permissões".
|
| O superadministrador NÃO é um perfil: é a flag users.is_super_admin,
| que concede acesso total via Gate::before (AppServiceProvider).
|
| Após alterar este arquivo, sincronize o banco com:
|   php artisan db:seed --class=PermissionSeeder
| (o perfil "company-admin" de cada empresa recebe as permissões novas;
| os demais perfis existentes não são alterados.)
*/

return [

    'groups' => [
        'Geral' => [
            'dashboard.view' => 'Visualizar dashboard',
            'reports.commercial' => 'Relatórios comerciais',
            'reports.financial' => 'Relatórios financeiros',
            'reports.operational' => 'Relatórios operacionais',
        ],
        'Administração' => [
            'settings.manage' => 'Gerenciar configurações da empresa',
            'units.view' => 'Visualizar unidades',
            'units.manage' => 'Gerenciar unidades',
            'users.view' => 'Visualizar usuários',
            'users.create' => 'Cadastrar usuários',
            'users.update' => 'Editar usuários',
            'users.deactivate' => 'Ativar/desativar usuários',
            'roles.view' => 'Visualizar perfis de acesso',
            'roles.manage' => 'Gerenciar perfis e permissões',
            'audit.view' => 'Consultar auditoria',
        ],
        'Clientes e fornecedores' => [
            'customers.view' => 'Visualizar clientes',
            'customers.create' => 'Cadastrar clientes',
            'customers.update' => 'Editar clientes',
            'customers.delete' => 'Excluir clientes',
            'customers.export' => 'Exportar clientes',
            'suppliers.view' => 'Visualizar fornecedores',
            'suppliers.create' => 'Cadastrar fornecedores',
            'suppliers.update' => 'Editar fornecedores',
            'suppliers.delete' => 'Excluir fornecedores',
            'suppliers.export' => 'Exportar fornecedores',
        ],
        'CRM' => [
            'leads.view' => 'Visualizar leads',
            'leads.create' => 'Cadastrar leads',
            'leads.update' => 'Editar leads',
            'leads.delete' => 'Excluir leads',
            'leads.convert' => 'Converter leads em clientes',
            'opportunities.view' => 'Visualizar oportunidades',
            'opportunities.create' => 'Cadastrar oportunidades',
            'opportunities.update' => 'Editar e mover oportunidades',
            'opportunities.delete' => 'Excluir oportunidades',
            'crm.configure' => 'Configurar funil e origens',
        ],
        'Catálogo' => [
            'catalog.view' => 'Visualizar catálogo',
            'catalog.create' => 'Cadastrar itens',
            'catalog.update' => 'Editar itens',
            'catalog.delete' => 'Excluir itens',
        ],
        'Propostas e contratos' => [
            'proposals.view' => 'Visualizar propostas',
            'proposals.create' => 'Criar propostas',
            'proposals.update' => 'Editar propostas',
            'proposals.delete' => 'Excluir propostas',
            'proposals.approve' => 'Enviar, aprovar e recusar propostas',
            'contracts.view' => 'Visualizar contratos',
            'contracts.create' => 'Criar contratos',
            'contracts.update' => 'Editar contratos',
            'contracts.manage' => 'Ativar, suspender, renovar e cancelar contratos',
        ],
        'Operações' => [
            'service_orders.view' => 'Visualizar ordens de serviço',
            'service_orders.create' => 'Abrir ordens de serviço',
            'service_orders.update' => 'Atualizar ordens de serviço',
            'service_orders.delete' => 'Excluir ordens de serviço',
            'tickets.view' => 'Visualizar chamados',
            'tickets.create' => 'Abrir chamados',
            'tickets.update' => 'Atender e atualizar chamados',
            'tickets.delete' => 'Excluir chamados',
        ],
        'Financeiro' => [
            'receivables.view' => 'Visualizar contas a receber',
            'receivables.create' => 'Lançar contas a receber',
            'receivables.update' => 'Editar contas a receber',
            'receivables.cancel' => 'Cancelar contas a receber',
            'receivables.settle' => 'Registrar recebimentos',
            'payables.view' => 'Visualizar contas a pagar',
            'payables.create' => 'Lançar contas a pagar',
            'payables.update' => 'Editar contas a pagar',
            'payables.cancel' => 'Cancelar contas a pagar',
            'payables.settle' => 'Registrar pagamentos',
            'payments.reverse' => 'Estornar pagamentos e transferências',
            'accounts.view' => 'Visualizar contas financeiras e extratos',
            'accounts.manage' => 'Gerenciar contas financeiras e lançamentos avulsos',
            'transfers.create' => 'Transferir entre contas',
            'cash_flow.view' => 'Visualizar fluxo de caixa',
            'finance.configure' => 'Configurar categorias, centros de custo e recorrências',
        ],
    ],

    'roles' => [
        'company-admin' => [
            'name' => 'Administrador da empresa',
            'description' => 'Acesso total aos módulos e à administração da empresa.',
            'permissions' => ['*'],
        ],
        'manager' => [
            'name' => 'Gerente',
            'description' => 'Gestão operacional, comercial e financeira, sem administrar usuários e perfis.',
            'permissions' => [
                'dashboard.view', 'reports.commercial', 'reports.financial', 'reports.operational',
                'units.view', 'users.view', 'roles.view', 'audit.view',
                'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.export',
                'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.export',
                'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.convert',
                'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.delete', 'crm.configure',
                'catalog.view', 'catalog.create', 'catalog.update',
                'proposals.view', 'proposals.create', 'proposals.update', 'proposals.delete', 'proposals.approve',
                'contracts.view', 'contracts.create', 'contracts.update', 'contracts.manage',
                'service_orders.view', 'service_orders.create', 'service_orders.update', 'service_orders.delete',
                'tickets.view', 'tickets.create', 'tickets.update', 'tickets.delete',
                'receivables.view', 'receivables.create', 'receivables.update', 'receivables.cancel', 'receivables.settle',
                'payables.view', 'payables.create', 'payables.update', 'payables.cancel', 'payables.settle',
                'accounts.view', 'transfers.create', 'cash_flow.view',
            ],
        ],
        'sales' => [
            'name' => 'Comercial',
            'description' => 'Clientes, CRM, propostas e criação de contratos.',
            'permissions' => [
                'dashboard.view', 'reports.commercial',
                'customers.view', 'customers.create', 'customers.update', 'customers.export',
                'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.convert',
                'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.delete',
                'catalog.view',
                'proposals.view', 'proposals.create', 'proposals.update', 'proposals.delete', 'proposals.approve',
                'contracts.view', 'contracts.create',
                'service_orders.view', 'tickets.view', 'tickets.create',
            ],
        ],
        'finance' => [
            'name' => 'Financeiro',
            'description' => 'Contas a receber e a pagar, contas financeiras e fluxo de caixa.',
            'permissions' => [
                'dashboard.view', 'reports.financial',
                'customers.view', 'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.export',
                'contracts.view',
                'receivables.view', 'receivables.create', 'receivables.update', 'receivables.cancel', 'receivables.settle',
                'payables.view', 'payables.create', 'payables.update', 'payables.cancel', 'payables.settle',
                'payments.reverse', 'accounts.view', 'accounts.manage', 'transfers.create', 'cash_flow.view',
                'finance.configure',
            ],
        ],
        'support' => [
            'name' => 'Atendimento',
            'description' => 'Chamados, ordens de serviço e consulta de clientes e contratos.',
            'permissions' => [
                'dashboard.view', 'reports.operational',
                'customers.view', 'customers.update', 'contracts.view', 'catalog.view',
                'service_orders.view', 'service_orders.create', 'service_orders.update',
                'tickets.view', 'tickets.create', 'tickets.update',
            ],
        ],
        'collaborator' => [
            'name' => 'Colaborador',
            'description' => 'Execução de ordens de serviço e atendimento básico.',
            'permissions' => [
                'dashboard.view', 'customers.view', 'catalog.view',
                'service_orders.view', 'service_orders.update',
                'tickets.view', 'tickets.create', 'tickets.update',
            ],
        ],
    ],

];
