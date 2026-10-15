<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tarefas agendadas (Scheduler)
|--------------------------------------------------------------------------
|
| Em produção, configure UMA entrada no cron do servidor:
|   * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
|
| Em desenvolvimento, deixe rodando em um terminal:
|   php artisan schedule:work
|
| As rotinas pesadas são enfileiradas (Jobs) e executadas pelo worker:
|   php artisan queue:work
*/

// Rotinas diárias de cada empresa: cobranças recorrentes, despesas
// recorrentes, expiração de propostas, vigência de contratos e avisos.
Schedule::command('erp:daily-routines')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Auditoria contábil: confere os saldos das contas com os extratos.
Schedule::command('finance:verify-balances')
    ->dailyAt('05:30')
    ->onOneServer();

// Limpeza de tokens de redefinição de senha expirados.
Schedule::command('auth:clear-resets')->daily();

// Remove tokens de API (Sanctum) expirados há mais de 48 horas.
Schedule::command('sanctum:prune-expired --hours=48')->daily();
