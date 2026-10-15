<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permissão do sistema no formato "recurso.ação" (ex.: "receivables.settle").
 *
 * A lista oficial fica em config/permissions.php e é sincronizada com o
 * banco pelo PermissionSeeder (php artisan db:seed --class=PermissionSeeder).
 */
#[Fillable(['name', 'group', 'label'])]
class Permission extends Model
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
