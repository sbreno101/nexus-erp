<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Vínculo entre um usuário e uma empresa (tabela pivô company_user).
 *
 * É um "Pivot model" customizado: além das chaves, guarda o perfil (role)
 * do usuário NAQUELA empresa, a unidade e se o vínculo está ativo.
 */
#[Fillable(['company_id', 'user_id', 'role_id', 'company_unit_id', 'is_active'])]
class CompanyMembership extends Pivot
{
    use Auditable;

    protected $table = 'company_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<CompanyUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CompanyUnit::class, 'company_unit_id');
    }
}
