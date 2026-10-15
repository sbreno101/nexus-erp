<?php

use App\Exceptions\BusinessRuleException;
use App\Services\LineItemCalculator;

beforeEach(fn () => $this->calculator = new LineItemCalculator);

it('calcula total dos itens, subtotal, desconto e total geral', function () {
    $result = $this->calculator->calculate([
        ['description' => 'Consultoria', 'quantity' => '10', 'unit_price_cents' => 25000, 'discount_cents' => 5000],
        ['description' => 'Implantação', 'quantity' => '1,5', 'unit_price_cents' => 100000],
    ], globalDiscountCents: 10000);

    expect($result['items'][0]['total_cents'])->toBe(245000)
        ->and($result['items'][1]['total_cents'])->toBe(150000)
        ->and($result['items'][1]['quantity'])->toBe('1.5')
        ->and($result['subtotal_cents'])->toBe(395000)
        ->and($result['discount_cents'])->toBe(10000)
        ->and($result['total_cents'])->toBe(385000);
});

it('não aceita desconto maior que o item', function () {
    $this->calculator->calculate([
        ['description' => 'Item', 'quantity' => '1', 'unit_price_cents' => 1000, 'discount_cents' => 2000],
    ]);
})->throws(BusinessRuleException::class);

it('não aceita desconto geral maior que o subtotal', function () {
    $this->calculator->calculate([
        ['description' => 'Item', 'quantity' => '1', 'unit_price_cents' => 1000],
    ], globalDiscountCents: 1001);
})->throws(BusinessRuleException::class);

it('exige ao menos um item e quantidade positiva', function () {
    expect(fn () => $this->calculator->calculate([]))->toThrow(BusinessRuleException::class)
        ->and(fn () => $this->calculator->calculate([
            ['description' => 'Item', 'quantity' => '0', 'unit_price_cents' => 1000],
        ]))->toThrow(BusinessRuleException::class);
});
