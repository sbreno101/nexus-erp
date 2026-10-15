<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Propostas comerciais e contratos (com seus itens).
 *
 * contracts.proposal_id é UNIQUE: uma proposta aprovada só pode originar um
 * contrato. Mesmo que dois usuários cliquem em "Converter" ao mesmo tempo, o
 * banco de dados rejeita a segunda conversão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('valid_until');
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->text('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'valid_until']);
        });

        Schema::create('proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            // Quantidade decimal exata (ex.: 1,5 hora). Nunca float.
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 10)->default('UN');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposal_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('company_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('billing_frequency', 20)->default('monthly');
            $table->unsignedTinyInteger('billing_day')->default(10);
            $table->unsignedBigInteger('amount_cents');
            $table->date('next_billing_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('renewal_months')->default(12);
            $table->date('signed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['status', 'next_billing_date']);
        });

        Schema::create('contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 10)->default('UN');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_discount_check CHECK (discount_cents <= subtotal_cents)');
        DB::statement('ALTER TABLE proposal_items ADD CONSTRAINT proposal_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE contract_items ADD CONSTRAINT contract_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE contracts ADD CONSTRAINT contracts_billing_day_check CHECK (billing_day BETWEEN 1 AND 28)');
        DB::statement('ALTER TABLE contracts ADD CONSTRAINT contracts_dates_check CHECK (end_date IS NULL OR end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_items');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('proposal_items');
        Schema::dropIfExists('proposals');
    }
};
