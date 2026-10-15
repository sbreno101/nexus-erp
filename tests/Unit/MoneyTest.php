<?php

use App\Support\Money;

describe('Money::format', function () {
    it('formata centavos no padrão brasileiro', function (int $cents, string $expected) {
        expect(Money::format($cents))->toBe($expected);
    })->with([
        [0, 'R$ 0,00'],
        [5, 'R$ 0,05'],
        [123456, 'R$ 1.234,56'],
        [100000000, 'R$ 1.000.000,00'],
        [-1050, '-R$ 10,50'],
    ]);
});

describe('Money::parse', function () {
    it('converte textos digitados em centavos sem usar float', function (string $input, int $expected) {
        expect(Money::parse($input))->toBe($expected);
    })->with([
        ['1.234,56', 123456],
        ['R$ 1.234,56', 123456],
        ['0,1', 10],
        ['10', 1000],
        ['1234.56', 123456],
        ['1.234', 123400],
        ['-5,25', -525],
    ]);

    it('rejeita valores inválidos', function (string $input) {
        Money::parse($input);
    })->with(['abc', '1,234,5', '12,345'])->throws(InvalidArgumentException::class);

    it('soma 0,10 + 0,20 exatamente como 0,30 (o clássico problema do float)', function () {
        expect(Money::parse('0,10') + Money::parse('0,20'))->toBe(Money::parse('0,30'));
    });
});

describe('Money::multiply', function () {
    it('multiplica preço por quantidade decimal com arredondamento meio-para-cima', function (int $unit, string $quantity, int $expected) {
        expect(Money::multiply($unit, $quantity))->toBe($expected);
    })->with([
        [15000, '2', 30000],
        [15000, '1,5', 22500],
        [999, '0.333', 333],
        [1, '0.5', 1],
        [33333, '3', 99999],
    ]);
});
