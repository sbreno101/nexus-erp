<?php

namespace App\Http\Requests\Operations;

use App\Enums\CatalogItemType;
use App\Enums\Priority;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de abertura/edição de ordens de serviço (web e API).
 *
 * Todas as regras "exists" são restritas à empresa ativa: sem isso, alguém
 * poderia enviar o id de um cliente de OUTRA empresa e vinculá-lo à OS.
 * O contrato, além de ser da empresa, precisa pertencer ao cliente da OS.
 */
class ServiceOrderRequest extends FormRequest
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

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')],
            'contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')
                ->where('company_id', $companyId)
                ->where('customer_id', (int) $this->input('customer_id'))
                ->whereNull('deleted_at')],
            'catalog_item_id' => ['nullable', 'integer', Rule::exists('catalog_items', 'id')
                ->where('company_id', $companyId)
                ->where('type', CatalogItemType::Service->value)
                ->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('company_user', 'user_id')
                ->where('company_id', $companyId)
                ->where('is_active', true)],
            'due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contract_id.exists' => 'O contrato selecionado não pertence a este cliente.',
            'catalog_item_id.exists' => 'Selecione um serviço válido do catálogo.',
            'assigned_to.exists' => 'O responsável precisa ser um usuário ativo desta empresa.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'catalog_item_id' => 'serviço',
            'due_date' => 'prazo',
        ];
    }
}
