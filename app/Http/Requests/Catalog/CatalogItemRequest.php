<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogItemType;
use App\Http\Requests\Concerns\NormalizesMoney;
use App\Models\CatalogItem;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do cadastro de itens do catálogo (tela web e API).
 * A autorização (Policy) é feita no Controller.
 *
 * - O código é gravado sempre em MAIÚSCULAS e é único por empresa.
 * - O preço chega como texto ("1.234,56") e vira price_cents (inteiro) antes
 *   da validação, graças ao trait NormalizesMoney.
 * - A categoria precisa ser da mesma empresa E do mesmo tipo do item
 *   (não faz sentido um serviço numa categoria de produtos).
 */
class CatalogItemRequest extends FormRequest
{
    use NormalizesMoney;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function moneyFields(): array
    {
        return ['price' => 'price_cents'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyFields();

        if ($this->filled('code')) {
            $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
        }

        if ($this->filled('unit')) {
            $this->merge(['unit' => trim((string) $this->input('unit'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'type' => ['required', Rule::enum(CatalogItemType::class)],
            'code' => [
                'required', 'string', 'max:30',
                // Espelha o índice UNIQUE parcial do banco (company_id, code)
                // WHERE deleted_at IS NULL: itens excluídos liberam o código.
                Rule::unique('catalog_items', 'code')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($this->currentId()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', 'string', 'max:10'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'catalog_category_id' => [
                'nullable', 'integer',
                Rule::exists('catalog_categories', 'id')
                    ->where('company_id', $companyId)
                    ->where('type', (string) $this->input('type')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Já existe um item com este código nesta empresa.',
            'catalog_category_id.exists' => 'Selecione uma categoria do mesmo tipo do item.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'catalog_category_id' => 'categoria',
            'is_active' => 'ativo',
        ];
    }

    /**
     * Id do item em edição (para ignorar na regra de código único).
     */
    private function currentId(): ?int
    {
        $catalogItem = $this->route('catalogItem');

        return $catalogItem instanceof CatalogItem ? $catalogItem->id : null;
    }
}
