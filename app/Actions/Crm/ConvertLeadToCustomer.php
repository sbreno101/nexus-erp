<?php

namespace App\Actions\Crm;

use App\Enums\CustomerStatus;
use App\Enums\LeadStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Converte um lead em cliente.
 *
 * O lead NÃO é apagado: fica marcado como "Convertido" e aponta para o
 * cliente criado (customer_id), preservando a origem comercial. As
 * oportunidades abertas do lead passam a apontar também para o cliente.
 */
class ConvertLeadToCustomer
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $customerData  dados já validados (ConvertLeadRequest)
     */
    public function handle(Lead $lead, array $customerData): Customer
    {
        return DB::transaction(function () use ($lead, $customerData): Customer {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($locked->isConverted()) {
                throw new BusinessRuleException('Este lead já foi convertido em cliente.');
            }

            $customer = Customer::create([
                ...$customerData,
                'status' => CustomerStatus::Active,
                'owner_id' => $customerData['owner_id'] ?? $locked->owner_id,
                'notes' => trim(($customerData['notes'] ?? '')."\nOrigem: lead #{$locked->id}"),
            ]);

            $locked->forceFill([
                'status' => LeadStatus::Converted,
                'customer_id' => $customer->id,
                'converted_at' => now(),
            ])->save();

            Opportunity::query()->where('lead_id', $locked->id)->whereNull('customer_id')
                ->update(['customer_id' => $customer->id]);

            $this->audit->log('lead.converted', $locked, null, ['customer_id' => $customer->id], "Lead convertido no cliente {$customer->name}.");

            $lead->setRawAttributes($locked->getAttributes(), sync: true);

            return $customer;
        });
    }
}
