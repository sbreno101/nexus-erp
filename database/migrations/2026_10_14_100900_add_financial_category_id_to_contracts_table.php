<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exemplo de EVOLUÇÃO de esquema: em vez de editar a migration original de
 * contratos (que já pode ter sido aplicada em outros ambientes), criamos uma
 * nova migration que adiciona a coluna.
 *
 * A categoria financeira do contrato é copiada para cada cobrança gerada,
 * permitindo relatórios de receita por categoria.
 */
return new class extends Migration
{
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
};
