<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas (tenants), suas configurações e unidades.
 *
 * A tabela "companies" é a raiz do isolamento multiempresa: quase todas as
 * demais tabelas possuem uma coluna company_id apontando para ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('cnpj', 14)->unique();
            $table->string('state_registration', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('zip_code', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->char('state', 2)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('logo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Relação 1:1 (hasOne) com companies: parâmetros operacionais da empresa.
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('primary_color', 7)->default('#4f46e5');
            $table->string('default_theme', 10)->default('system');
            $table->unsignedSmallInteger('items_per_page')->default(15);
            $table->string('proposal_prefix', 10)->default('PROP');
            $table->string('contract_prefix', 10)->default('CTR');
            $table->string('service_order_prefix', 10)->default('OS');
            $table->string('ticket_prefix', 10)->default('CHM');
            $table->unsignedSmallInteger('proposal_validity_days')->default(15);
            $table->text('default_payment_terms')->nullable();
            $table->unsignedTinyInteger('default_billing_day')->default(10);
            $table->unsignedSmallInteger('billing_days_in_advance')->default(10);
            $table->boolean('notify_overdue_receivables')->default(true);
            $table->boolean('notify_ticket_assignment')->default(true);
            $table->boolean('notify_service_order_assignment')->default(true);
            $table->boolean('notify_proposal_status')->default(true);
            $table->string('notification_email')->nullable();
            $table->timestamps();
        });

        Schema::create('company_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->string('cnpj', 14)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('zip_code', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->char('state', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_units');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('companies');
    }
};
