<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes e fornecedores.
 *
 * Regra de duplicidade: o mesmo CPF/CNPJ não pode ser cadastrado duas vezes
 * na MESMA empresa (empresas diferentes podem ter o mesmo cliente). Usamos
 * um índice UNIQUE PARCIAL do PostgreSQL ("WHERE deleted_at IS NULL") para
 * que um cadastro excluído (soft delete) não bloqueie um novo cadastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'suppliers'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20);
                $table->string('name');
                $table->string('trade_name')->nullable();
                $table->string('document', 14);
                $table->string('state_registration', 30)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('mobile', 20)->nullable();
                $table->string('website')->nullable();
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();

                if ($tableName === 'customers') {
                    $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                }

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'name']);
            });

            DB::statement(
                "CREATE UNIQUE INDEX {$tableName}_company_document_unique
                 ON {$tableName} (company_id, document) WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
