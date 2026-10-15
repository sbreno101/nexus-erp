<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Auditoria automática de criação, alteração e exclusão de um Model.
 *
 * Usa os eventos do Eloquent (created, updated, deleted, restored). Um Model
 * pode definir a propriedade $auditExclude para ignorar colunas irrelevantes.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->log('created', $model, null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            app(AuditLogger::class)->log('updated', $model, $model->auditableAttributes($original), $changes);
        });

        static::deleted(function (Model $model): void {
            app(AuditLogger::class)->log('deleted', $model);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                app(AuditLogger::class)->log('restored', $model);
            });
        }
    }

    /**
     * Histórico de auditoria deste registro.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    /**
     * Remove colunas que não interessam à auditoria.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        $excluded = array_merge(
            ['created_at', 'updated_at', 'remember_token', 'password'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return array_diff_key($attributes, array_flip($excluded));
    }
}
