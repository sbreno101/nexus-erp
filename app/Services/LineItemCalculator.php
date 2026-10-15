<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Support\Money;

/**
 * Calcula totais de itens de propostas e contratos — sempre em centavos.
 *
 *   total do item = (preço unitário × quantidade) − desconto do item
 *   subtotal      = soma dos totais dos itens
 *   total geral   = subtotal − desconto geral
 *
 * Mantido em uma classe própria (e não no Model ou no Controller) para que
 * a MESMA regra seja usada pela tela web, pela API e pelos testes.
 */
class LineItemCalculator
{
    /**
     * @param  list<array{description: string, quantity: string|int, unit_price_cents: int, discount_cents?: int|null, catalog_item_id?: int|null, unit?: string|null}>  $items
     * @return array{items: list<array<string, mixed>>, subtotal_cents: int, discount_cents: int, total_cents: int}
     */
    public function calculate(array $items, int $globalDiscountCents = 0): array
    {
        if ($items === []) {
            throw new BusinessRuleException('Informe pelo menos um item.');
        }

        $calculatedItems = [];
        $subtotal = 0;

        foreach (array_values($items) as $position => $item) {
            $quantity = Money::normalizeDecimal($item['quantity']);
            $unitPrice = (int) $item['unit_price_cents'];
            $discount = (int) ($item['discount_cents'] ?? 0);

            if (bccomp($quantity, '0', 3) <= 0) {
                throw new BusinessRuleException("A quantidade do item \"{$item['description']}\" deve ser maior que zero.");
            }

            $gross = Money::multiply($unitPrice, $quantity);

            if ($discount < 0 || $discount > $gross) {
                throw new BusinessRuleException("O desconto do item \"{$item['description']}\" não pode ser maior que o valor do item.");
            }

            $total = $gross - $discount;
            $subtotal += $total;

            $calculatedItems[] = [
                'catalog_item_id' => $item['catalog_item_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? 'UN',
                'unit_price_cents' => $unitPrice,
                'discount_cents' => $discount,
                'total_cents' => $total,
                'position' => $position,
            ];
        }

        if ($globalDiscountCents < 0 || $globalDiscountCents > $subtotal) {
            throw new BusinessRuleException('O desconto geral não pode ser maior que o subtotal.');
        }

        return [
            'items' => $calculatedItems,
            'subtotal_cents' => $subtotal,
            'discount_cents' => $globalDiscountCents,
            'total_cents' => $subtotal - $globalDiscountCents,
        ];
    }
}
