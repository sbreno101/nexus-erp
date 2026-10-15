-- Executado automaticamente pelo container do PostgreSQL na primeira inicialização.
-- Cria o banco isolado usado pela suíte de testes (Pest).
SELECT 'CREATE DATABASE nexus_erp_testing OWNER ' || current_user
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'nexus_erp_testing')\gexec
