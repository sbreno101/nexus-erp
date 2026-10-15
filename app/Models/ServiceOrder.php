<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ServiceOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ordem de serviço (execução de um trabalho para um cliente).
 */
#[Fillable([
    'number', 'customer_id', 'contract_id', 'catalog_item_id', 'title', 'description',
    'priority', 'status', 'assigned_to', 'created_by', 'due_date', 'resolution',
])]
class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use Auditable, BelongsToCompany, HasAttachments, HasFactory, HasStatusHistory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => ServiceOrderStatus::class,
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Serviço do catálogo relacionado.
     *
     * @return BelongsTo<CatalogItem, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ServiceOrderActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ServiceOrderActivity::class)->latest();
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<ServiceOrder>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', ServiceOrderStatus::pending());
    }

    /**
     * @param  Builder<ServiceOrder>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->whereAny(['number', 'title'], 'ilike', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $customer) => $customer->search($term));
        });
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ServiceOrderStatus::pending(), true)
            && $this->due_date !== null
            && $this->due_date->endOfDay()->isPast();
    }

    public function totalMinutesSpent(): int
    {
        return (int) $this->activities->sum('minutes_spent');
    }
}
