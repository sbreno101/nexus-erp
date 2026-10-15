<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle de acesso: colunas extras em users, permissões, perfis (roles)
 * por empresa e o vínculo usuário <-> empresa (company_user).
 *
 * Modelo adotado:
 *   - permissions: lista global de ações do sistema (ex.: "customers.view").
 *   - roles: perfis pertencentes a UMA empresa (cada empresa pode ajustar os seus).
 *   - permission_role: quais permissões cada perfil possui (N:N).
 *   - company_user: o usuário participa de N empresas com UM perfil em cada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_super_admin');
            $table->foreignId('current_company_id')->nullable()->after('is_active')
                ->constrained('companies')->nullOnDelete();
            $table->string('theme', 10)->default('system')->after('current_company_id');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('deactivated_at')->nullable();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('group', 50)->index();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_company_id');
            $table->dropColumn([
                'phone', 'job_title', 'is_super_admin', 'is_active', 'theme',
                'last_login_at', 'last_login_ip', 'deactivated_at',
            ]);
        });
    }
};
