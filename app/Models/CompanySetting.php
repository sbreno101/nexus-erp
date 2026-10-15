<?php

namespace App\Models;

use App\Enums\ThemePreference;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configurações individuais de uma empresa (relação 1:1 com Company).
 *
 * Optamos por colunas explícitas (e não um JSON genérico) para ter tipos,
 * valores padrão e validação garantidos pelo banco de dados.
 */
#[Fillable([
    'primary_color', 'default_theme', 'items_per_page',
    'proposal_prefix', 'contract_prefix', 'service_order_prefix', 'ticket_prefix',
    'proposal_validity_days', 'default_payment_terms', 'default_billing_day',
    'billing_days_in_advance', 'notify_overdue_receivables', 'notify_ticket_assignment',
    'notify_service_order_assignment', 'notify_proposal_status', 'notification_email',
])]
class CompanySetting extends Model
{
    use Auditable;

    protected $attributes = [
        'primary_color' => '#4f46e5',
        'default_theme' => 'system',
        'items_per_page' => 15,
        'proposal_prefix' => 'PROP',
        'contract_prefix' => 'CTR',
        'service_order_prefix' => 'OS',
        'ticket_prefix' => 'CHM',
        'proposal_validity_days' => 15,
        'default_billing_day' => 10,
        'billing_days_in_advance' => 10,
        'notify_overdue_receivables' => true,
        'notify_ticket_assignment' => true,
        'notify_service_order_assignment' => true,
        'notify_proposal_status' => true,
    ];

    protected function casts(): array
    {
        return [
            'default_theme' => ThemePreference::class,
            'items_per_page' => 'integer',
            'proposal_validity_days' => 'integer',
            'default_billing_day' => 'integer',
            'billing_days_in_advance' => 'integer',
            'notify_overdue_receivables' => 'boolean',
            'notify_ticket_assignment' => 'boolean',
            'notify_service_order_assignment' => 'boolean',
            'notify_proposal_status' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
