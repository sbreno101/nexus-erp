<?php

namespace App\Models;

use App\Enums\PersonType;
use App\Enums\RecordStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasContactDetails;
use App\Models\Concerns\IsBusinessParty;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Fornecedor (origem das contas a pagar).
 *
 * @property int $id
 * @property int $company_id
 * @property PersonType $type
 * @property string $name
 * @property string|null $trade_name
 * @property string $document
 * @property string|null $state_registration
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $website
 * @property RecordStatus $status
 * @property string|null $notes
 * @property-read string $display_name
 * @property-read string $formatted_document
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type', 'name', 'trade_name', 'document', 'state_registration', 'email',
    'phone', 'mobile', 'website', 'status', 'notes',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable, BelongsToCompany, HasAttachments, HasContactDetails, HasFactory, IsBusinessParty, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => PersonType::class,
            'status' => RecordStatus::class,
        ];
    }

    /**
     * @return HasMany<Payable, $this>
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    /**
     * @return HasMany<RecurringExpense, $this>
     */
    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class);
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', RecordStatus::Active);
    }
}
