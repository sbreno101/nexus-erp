<?php

namespace App\Http\Requests\Settings;

use App\Enums\ThemePreference;
use App\Http\Requests\Customers\BusinessPartyRequest;
use App\Rules\CpfCnpj;
use App\Support\BrazilianDocument;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da tela "Configurações da empresa".
 *
 * Um único formulário edita DOIS models: Company (dados institucionais) e
 * CompanySetting (preferências e parâmetros). Os métodos companyData() e
 * settingsData() separam os campos de cada um, porque o
 * preventSilentlyDiscardingAttributes() não aceita chaves desconhecidas.
 */
class CompanySettingsRequest extends FormRequest
{
    /**
     * Campos do model Company editáveis nesta tela ("status" e "logo_path"
     * ficam de fora: status é exclusivo do superadministrador e o logotipo
     * é tratado como upload).
     *
     * @var list<string>
     */
    public const array COMPANY_FIELDS = [
        'legal_name', 'trade_name', 'cnpj', 'state_registration', 'email', 'phone',
        'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state',
    ];

    /**
     * Campos do model CompanySetting.
     *
     * @var list<string>
     */
    public const array SETTINGS_FIELDS = [
        'primary_color', 'default_theme', 'items_per_page',
        'proposal_prefix', 'contract_prefix', 'service_order_prefix', 'ticket_prefix',
        'proposal_validity_days', 'default_payment_terms', 'default_billing_day',
        'billing_days_in_advance', 'notify_overdue_receivables', 'notify_ticket_assignment',
        'notify_service_order_assignment', 'notify_proposal_status', 'notification_email',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza os dados ANTES da validação: documentos e CEP sem máscara,
     * UF e prefixos em maiúsculas.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => BrazilianDocument::clean($this->input('cnpj')),
            'zip_code' => $this->filled('zip_code') ? preg_replace('/\D/', '', (string) $this->input('zip_code')) : null,
            'state' => $this->filled('state') ? strtoupper((string) $this->input('state')) : null,
            'proposal_prefix' => strtoupper(trim((string) $this->input('proposal_prefix'))),
            'contract_prefix' => strtoupper(trim((string) $this->input('contract_prefix'))),
            'service_order_prefix' => strtoupper(trim((string) $this->input('service_order_prefix'))),
            'ticket_prefix' => strtoupper(trim((string) $this->input('ticket_prefix'))),
            'notification_email' => $this->filled('notification_email') ? mb_strtolower(trim((string) $this->input('notification_email'))) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $prefixRules = ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/'];

        return [
            // Dados institucionais
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'required',
                new CpfCnpj('cnpj'),
                // O índice UNIQUE de companies.cnpj é global (inclui excluídas).
                Rule::unique('companies', 'cnpj')->ignore(app(CurrentCompany::class)->id()),
            ],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],

            // Endereço
            'zip_code' => ['nullable', 'digits:8'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(BusinessPartyRequest::STATES)],

            // Logotipo (arquivo opcional; max em kilobytes)
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],

            // Preferências de interface
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'default_theme' => ['required', Rule::enum(ThemePreference::class)],
            'items_per_page' => ['required', 'integer', 'between:10,100'],

            // Parâmetros operacionais
            'proposal_prefix' => $prefixRules,
            'contract_prefix' => $prefixRules,
            'service_order_prefix' => $prefixRules,
            'ticket_prefix' => $prefixRules,
            'proposal_validity_days' => ['required', 'integer', 'between:1,365'],
            'default_payment_terms' => ['nullable', 'string', 'max:2000'],
            'default_billing_day' => ['required', 'integer', 'between:1,28'],
            'billing_days_in_advance' => ['required', 'integer', 'between:0,60'],

            // Notificações
            'notify_overdue_receivables' => ['required', 'boolean'],
            'notify_ticket_assignment' => ['required', 'boolean'],
            'notify_service_order_assignment' => ['required', 'boolean'],
            'notify_proposal_status' => ['required', 'boolean'],
            'notification_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnpj.unique' => 'Já existe outra empresa cadastrada com este CNPJ.',
            'primary_color.regex' => 'Informe a cor no formato hexadecimal (ex.: #4f46e5).',
            '*_prefix.regex' => 'Use apenas letras, números e hífen no prefixo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'razão social',
            'trade_name' => 'nome fantasia',
            'cnpj' => 'CNPJ',
            'logo' => 'logotipo',
            'primary_color' => 'cor primária',
            'default_theme' => 'tema padrão',
            'items_per_page' => 'itens por página',
            'proposal_prefix' => 'prefixo de propostas',
            'contract_prefix' => 'prefixo de contratos',
            'service_order_prefix' => 'prefixo de ordens de serviço',
            'ticket_prefix' => 'prefixo de chamados',
            'proposal_validity_days' => 'validade padrão das propostas',
            'default_payment_terms' => 'condições de pagamento padrão',
            'default_billing_day' => 'dia de cobrança padrão',
            'billing_days_in_advance' => 'antecedência de geração das cobranças',
            'notification_email' => 'e-mail de notificações',
        ];
    }

    /**
     * Dados do model Company.
     *
     * @return array<string, mixed>
     */
    public function companyData(): array
    {
        return $this->safe()->only(self::COMPANY_FIELDS);
    }

    /**
     * Dados do model CompanySetting (booleanos convertidos explicitamente).
     *
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        $data = $this->safe()->only(self::SETTINGS_FIELDS);

        foreach (['notify_overdue_receivables', 'notify_ticket_assignment', 'notify_service_order_assignment', 'notify_proposal_status'] as $flag) {
            $data[$flag] = $this->boolean($flag);
        }

        return $data;
    }
}
