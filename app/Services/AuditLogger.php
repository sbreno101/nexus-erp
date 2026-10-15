<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registra operações relevantes na trilha de auditoria (tabela audit_logs).
 *
 * É chamado de duas formas:
 *   - automaticamente, pelo trait Auditable (created/updated/deleted);
 *   - explicitamente, pelas Actions de negócio (ex.: "proposal.approved",
 *     "payment.reversed"), que descrevem a INTENÇÃO da operação.
 *
 * Campos sensíveis nunca são gravados (veja SENSITIVE_KEYS).
 */
class AuditLogger
{
    /**
     * Atributos que jamais devem ir para a auditoria.
     */
    public const array SENSITIVE_KEYS = [
        'password', 'remember_token', 'token', 'two_factor_secret',
        'two_factor_recovery_codes', 'api_token', 'secret',
    ];

    private static bool $enabled = true;

    public function __construct(private CurrentCompany $currentCompany) {}

    /**
     * Executa um trecho sem gerar auditoria (ex.: carga de dados demonstrativos).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $event,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
    ): ?AuditLog {
        if (! self::$enabled) {
            return null;
        }

        try {
            $request = app()->runningInConsole() ? null : request();

            // Transação aninhada (SAVEPOINT): se o INSERT falhar dentro de uma
            // transação maior, o PostgreSQL desfaz só este trecho, e não aborta
            // a operação principal ("current transaction is aborted").
            return DB::transaction(fn (): AuditLog => AuditLog::create([
                'company_id' => $subject?->getAttribute('company_id') ?? $this->currentCompany->id(),
                'user_id' => Auth::id(),
                'event' => $event,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'description' => $description,
                'old_values' => $this->sanitize($oldValues),
                'new_values' => $this->sanitize($newValues),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ]));
        } catch (Throwable $exception) {
            // A auditoria nunca deve derrubar a operação principal; registramos no log técnico.
            Log::error('Falha ao gravar auditoria', ['event' => $event, 'exception' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null || $values === []) {
            return null;
        }

        foreach (array_keys($values) as $key) {
            if (in_array($key, self::SENSITIVE_KEYS, true)) {
                $values[$key] = '[protegido]';
            }
        }

        return $values;
    }
}
