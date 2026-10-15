<?php

namespace App\Models\Concerns;

use App\Enums\BillStatus;
use App\Enums\EntryStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Comportamento comum aos títulos financeiros (Receivable e Payable):
 * saldo devedor, vencimento, aplicação e reversão de pagamentos.
 *
 * O Model que usa este trait deve definir a constante PAYMENT_FOREIGN_KEY
 * ("receivable_id" ou "payable_id").
 *
 * @mixin Model
 */
trait IsBill
{
    public function initializeIsBill(): void
    {
        // Valores padrão também em memória (não só no banco), para que um título
        // recém-criado já tenha status e valor pago corretos sem recarregar.
        $this->attributes = array_merge(['status' => BillStatus::Open->value, 'paid_cents' => 0], $this->attributes);

        $this->mergeCasts([
            'status' => BillStatus::class,
            'amount_cents' => 'integer',
            'paid_cents' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'competence' => 'date',
            'paid_at' => 'date',
            'cancelled_at' => 'datetime',
        ]);
    }

    /**
     * Todos os pagamentos (confirmados e estornados) — histórico completo.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, static::PAYMENT_FOREIGN_KEY)->latest('paid_on')->latest('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function confirmedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, static::PAYMENT_FOREIGN_KEY)->where('status', EntryStatus::Confirmed);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Valor ainda em aberto (centavos).
     */
    public function outstandingCents(): int
    {
        return $this->status === BillStatus::Cancelled ? 0 : $this->amount_cents - $this->paid_cents;
    }

    public function isOverdue(): bool
    {
        return $this->status->acceptsPayment() && $this->due_date->lt(Carbon::today());
    }

    /**
     * Rótulo de status considerando o vencimento ("Vencido" é derivado).
     */
    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Vencido' : $this->status->label();
    }

    public function statusColor(): string
    {
        return $this->isOverdue() ? 'red' : $this->status->color();
    }

    /**
     * Aplica um pagamento ao título, atualizando valor pago e status.
     * Chamado somente pela Action RecordPayment, dentro de uma transação.
     */
    public function applyPayment(int $amountCents, Carbon $paidOn): void
    {
        if ($amountCents <= 0 || $amountCents > $this->outstandingCents()) {
            throw new LogicException('Valor de pagamento inválido para o saldo do título.');
        }

        $this->paid_cents += $amountCents;
        $this->status = $this->paid_cents === $this->amount_cents ? BillStatus::Paid : BillStatus::Partial;
        $this->paid_at = $this->status === BillStatus::Paid ? $paidOn : null;
        $this->save();
    }

    /**
     * Desfaz um pagamento (estorno). Chamado pela Action ReversePayment.
     */
    public function revertPayment(int $amountCents): void
    {
        if ($amountCents <= 0 || $amountCents > $this->paid_cents) {
            throw new LogicException('Valor de estorno inválido para o título.');
        }

        $this->paid_cents -= $amountCents;
        $this->status = $this->paid_cents === 0 ? BillStatus::Open : BillStatus::Partial;
        $this->paid_at = null;
        $this->save();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', BillStatus::outstanding());
    }

    /**
     * Títulos vencidos: em aberto/parciais com vencimento anterior a hoje.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', BillStatus::outstanding())->whereDate('due_date', '<', Carbon::today());
    }

    /**
     * Títulos a vencer (hoje ou no futuro) ainda não quitados.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereIn('status', BillStatus::outstanding())->whereDate('due_date', '>=', Carbon::today());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeDueBetween(Builder $query, Carbon|string $from, Carbon|string $to): void
    {
        $query->whereBetween('due_date', [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()]);
    }
}
