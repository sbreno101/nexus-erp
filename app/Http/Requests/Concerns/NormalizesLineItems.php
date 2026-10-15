<?php

namespace App\Http\Requests\Concerns;

use App\Support\Money;
use Illuminate\Validation\Rule;

/**
 * Normaliza a lista de itens (propostas e contratos) antes da validação:
 *  - "unit_price" e "discount" digitados ("1.234,56") viram centavos;
 *  - a quantidade "1,5" vira "1.5" (formato aceito pelo bcmath e pelo PostgreSQL).
 *
 * Pela API, o cliente pode enviar diretamente unit_price_cents/discount_cents.
 */
trait NormalizesLineItems
{
    protected function normalizeLineItems(): void
    {
        if (! is_array($this->input('items'))) {
            return;
        }

        $items = [];

        foreach (array_values($this->input('items')) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_key_exists('unit_price', $item)) {
                $item['unit_price_cents'] = filled($item['unit_price']) ? (Money::tryParse((string) $item['unit_price']) ?? 'invalid') : null;
            }

            if (array_key_exists('discount', $item)) {
                $item['discount_cents'] = filled($item['discount']) ? (Money::tryParse((string) $item['discount']) ?? 'invalid') : 0;
            }

            if (isset($item['quantity']) && is_string($item['quantity']) && str_contains($item['quantity'], ',')) {
                $item['quantity'] = str_replace(['.', ','], ['', '.'], $item['quantity']);
            }

            unset($item['unit_price'], $item['discount'], $item['key']);
            $items[] = $item;
        }

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function lineItemRules(int $companyId): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.catalog_item_id' => ['nullable', 'integer', Rule::exists('catalog_items', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit' => ['nullable', 'string', 'max:10'],
            'items.*.unit_price_cents' => ['required', 'integer', 'min:0'],
            'items.*.discount_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
