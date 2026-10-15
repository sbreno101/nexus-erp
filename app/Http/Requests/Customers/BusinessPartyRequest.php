<?php

namespace App\Http\Requests\Customers;

use App\Enums\PersonType;
use App\Rules\CpfCnpj;
use App\Support\BrazilianDocument;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras comuns a clientes e fornecedores.
 *
 * Duplicidade: o CPF/CNPJ é único POR EMPRESA entre registros não excluídos.
 * A regra "unique" do Laravel replica no formulário a mesma restrição do
 * índice UNIQUE parcial do banco, para mostrar uma mensagem amigável.
 */
abstract class BusinessPartyRequest extends FormRequest
{
    public const array STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    /**
     * Tabela do registro ("customers" ou "suppliers").
     */
    abstract protected function table(): string;

    /**
     * Id do registro em edição (para ignorar na regra de duplicidade).
     */
    abstract protected function currentId(): ?int;

    /**
     * Regras específicas do tipo de registro.
     *
     * @return array<string, mixed>
     */
    protected function extraRules(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['document' => BrazilianDocument::clean($this->input('document'))]);

        // Listas só são normalizadas quando enviadas: na API, omitir "addresses"
        // significa "não alterar os endereços" (e não "apagar todos").
        if (is_array($this->input('addresses'))) {
            $this->merge(['addresses' => collect($this->input('addresses'))->map(fn ($address) => [
                ...(array) $address,
                'zip_code' => isset($address['zip_code']) ? preg_replace('/\D/', '', (string) $address['zip_code']) : null,
                'state' => isset($address['state']) ? strtoupper((string) $address['state']) : null,
            ])->values()->all()]);
        }

        if (is_array($this->input('contacts'))) {
            $this->merge(['contacts' => array_values($this->input('contacts'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = PersonType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(PersonType::class)],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'required',
                new CpfCnpj($type?->documentRule()),
                Rule::unique($this->table(), 'document')
                    ->where('company_id', app(CurrentCompany::class)->id())
                    ->whereNull('deleted_at')
                    ->ignore($this->currentId()),
            ],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'addresses' => ['array', 'max:10'],
            'addresses.*.id' => ['nullable', 'integer'],
            'addresses.*.label' => ['nullable', 'string', 'max:50'],
            'addresses.*.zip_code' => ['nullable', 'digits:8'],
            'addresses.*.street' => ['required', 'string', 'max:255'],
            'addresses.*.number' => ['nullable', 'string', 'max:20'],
            'addresses.*.complement' => ['nullable', 'string', 'max:255'],
            'addresses.*.district' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['required', 'string', 'max:255'],
            'addresses.*.state' => ['required', Rule::in(self::STATES)],
            'addresses.*.is_primary' => ['nullable', 'boolean'],

            'contacts' => ['array', 'max:20'],
            'contacts.*.id' => ['nullable', 'integer'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.position' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:20'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],

            ...$this->extraRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.unique' => 'Já existe um cadastro com este CPF/CNPJ nesta empresa.',
        ];
    }

    /**
     * Dados do registro principal (sem as listas de endereços e contatos).
     *
     * @return array<string, mixed>
     */
    public function partyData(): array
    {
        return $this->safe()->except(['addresses', 'contacts']);
    }
}
