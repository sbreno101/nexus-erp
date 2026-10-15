<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Popula o ambiente de DESENVOLVIMENTO com dados demonstrativos coerentes.
 *
 *   php artisan migrate:fresh --seed
 *
 * Nunca executa em produção. Credenciais de acesso: veja o README.md.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        if (app()->isProduction()) {
            $this->command?->warn('Ambiente de produção: dados demonstrativos NÃO foram criados.');

            return;
        }

        // Idempotente: se os dados demo já existem, não duplica nem apaga nada.
        if (User::query()->where('email', 'admin@nexuserp.local')->exists()) {
            $this->command?->info('Dados demonstrativos já existem — nada a fazer. Para recriar do zero: php artisan migrate:fresh --seed');

            return;
        }

        // Durante a carga: e-mails descartados, filas síncronas e sem auditoria em massa.
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        AuditLogger::withoutAuditing(fn () => $this->call(DemoDataSeeder::class));

        Auth::forgetUser();
    }
}
