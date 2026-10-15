<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evolução de esquema: os códigos de itens do catálogo e de unidades devem
 * ser únicos apenas entre registros NÃO excluídos (soft delete), permitindo
 * reaproveitar o código de um item removido — mesma regra já usada para o
 * CPF/CNPJ de clientes e fornecedores.
 *
 * O índice UNIQUE comum é substituído por um índice UNIQUE PARCIAL do PostgreSQL.
 */
return new class extends Migration
{
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
};
