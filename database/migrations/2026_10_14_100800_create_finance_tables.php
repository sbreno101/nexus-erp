<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo financeiro.
 *
 * Decisões importantes (veja docs/06-regras-de-negocio.md):
 *   - Valores em CENTAVOS (bigint). Nunca float.
 *   - Nenhuma tabela financeira usa soft delete para pagamentos/lançamentos:
 *     eles NUNCA são apagados; erros são corrigidos por ESTORNO.
 *   - payments.idempotency_key e transfers.idempotency_key são UNIQUE por
 *     empresa: reenviar o mesmo formulário não duplica a operação.
 *   - receivables(contract_id, competence) é UNIQUE: o mesmo contrato não
 *     pode ser cobrado duas vezes na mesma competência.
 *   - CHECK constraints garantem no banco que o valor pago nunca supera o
 *     valor do título, mesmo que a aplicação tenha um bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'type', 'name']);
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_code', 5)->nullable();
            $table->string('agency', 20)->nullable();
            $table->string('account_number', 30)->nullable();
            $table->bigInteger('opening_balance_cents')->default(0);
            // Saldo "materializado": atualizado na mesma transação de cada
            // lançamento. Pode ser conferido a qualquer momento contra o
            // extrato (comando finance:verify-balances).
            $table->bigInteger('current_balance_cents')->default(0);
            $table->date('opening_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount_cents');
            $table->string('frequency', 20)->default('monthly');
            $table->unsignedTinyInteger('due_day');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_due_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('document_number', 50)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('competence');
            $table->string('status', 20)->default('open');
            $table->date('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'due_date']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'competence']);
        });

        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('recurring_expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('document_number', 50)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('competence');
            $table->string('status', 20)->default('open');
            $table->date('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'due_date']);
            $table->index(['company_id', 'supplier_id']);
            $table->index(['company_id', 'competence']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('transferred_on');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->uuid('idempotency_key');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receivable_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payable_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('notes')->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->uuid('idempotency_key');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'paid_on']);
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('direction', 3);
            $table->unsignedBigInteger('amount_cents');
            $table->bigInteger('balance_after_cents');
            $table->string('description');
            $table->date('occurred_on');
            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('financial_transactions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'occurred_on']);
            $table->index(['financial_account_id', 'occurred_on']);
        });

        // Integridade garantida pelo próprio PostgreSQL.
        DB::statement('ALTER TABLE receivables ADD CONSTRAINT receivables_amount_check CHECK (amount_cents > 0 AND paid_cents <= amount_cents)');
        DB::statement('ALTER TABLE payables ADD CONSTRAINT payables_amount_check CHECK (amount_cents > 0 AND paid_cents <= amount_cents)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_target_check CHECK ((receivable_id IS NULL) <> (payable_id IS NULL))');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_amount_check CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_accounts_check CHECK (from_account_id <> to_account_id)');
        DB::statement('ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_amount_check CHECK (amount_cents > 0)');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_direction_check CHECK (direction IN ('in', 'out'))");
        DB::statement('ALTER TABLE recurring_expenses ADD CONSTRAINT recurring_expenses_due_day_check CHECK (due_day BETWEEN 1 AND 28)');

        // Impede cobrança duplicada de um contrato na mesma competência e
        // geração duplicada de uma despesa recorrente no mesmo período.
        DB::statement('CREATE UNIQUE INDEX receivables_contract_competence_unique ON receivables (contract_id, competence) WHERE contract_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX payables_recurring_competence_unique ON payables (recurring_expense_id, competence) WHERE recurring_expense_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('payables');
        Schema::dropIfExists('receivables');
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('financial_categories');
    }
};
