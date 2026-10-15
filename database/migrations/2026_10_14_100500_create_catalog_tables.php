<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de produtos e serviços.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->timestamps();

            $table->unique(['company_id', 'type', 'name']);
        });

        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 10)->default('UN');
            $table->unsignedBigInteger('price_cents');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('catalog_categories');
    }
};
