<?php

namespace App\Http\Requests\Operations;

use App\Enums\Priority;
use App\Enums\TicketChannel;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de abertura/edição de chamados (web e API).
 *
 * Contrato e OS vinculados são opcionais, mas, se informados, precisam ser
 * da empresa ativa E do mesmo cliente do chamado.
 */
class TicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();
        $customerId = (int) $this->input('customer_id');

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')],
            'contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at')],
            'service_order_id' => ['nullable', 'integer', Rule::exists('service_orders', 'id')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at')],
            'ticket_category_id' => ['nullable', 'integer', Rule::exists('ticket_categories', 'id')
                ->where('company_id', $companyId)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'channel' => ['required', Rule::enum(TicketChannel::class)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')
                ->where('company_id', $companyId)
                ->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contract_id.exists' => 'O contrato selecionado não pertence a este cliente.',
            'service_order_id.exists' => 'A ordem de serviço selecionada não pertence a este cliente.',
            'assigned_to.exists' => 'O responsável precisa ser um usuário ativo desta empresa.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'service_order_id' => 'ordem de serviço',
            'ticket_category_id' => 'categoria',
            'channel' => 'canal',
        ];
    }
}
