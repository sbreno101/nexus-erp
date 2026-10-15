<?php

use App\Rules\CpfCnpj;
use App\Support\BrazilianDocument;
use Illuminate\Support\Facades\Validator;

it('valida CPFs corretos e rejeita inválidos', function (string $cpf, bool $valid) {
    expect(BrazilianDocument::isValidCpf($cpf))->toBe($valid);
})->with([
    ['529.982.247-25', true],
    ['52998224725', true],
    ['529.982.247-24', false],
    ['111.111.111-11', false],
    ['123', false],
]);

it('valida CNPJs numéricos e o novo formato alfanumérico', function (string $cnpj, bool $valid) {
    expect(BrazilianDocument::isValidCnpj($cnpj))->toBe($valid);
})->with([
    ['11.222.333/0001-81', true],
    ['11222333000181', true],
    ['11.222.333/0001-82', false],
    ['00.000.000/0000-00', false],
    ['12.ABC.345/01DE-35', true],
]);

it('gera documentos válidos para os dados de desenvolvimento', function () {
    foreach (range(1, 20) as $attempt) {
        expect(BrazilianDocument::isValidCpf(BrazilianDocument::generateCpf()))->toBeTrue()
            ->and(BrazilianDocument::isValidCnpj(BrazilianDocument::generateCnpj()))->toBeTrue();
    }
});

it('formata e limpa máscaras', function () {
    expect(BrazilianDocument::format('52998224725'))->toBe('529.982.247-25')
        ->and(BrazilianDocument::format('11222333000181'))->toBe('11.222.333/0001-81')
        ->and(BrazilianDocument::clean('11.222.333/0001-81'))->toBe('11222333000181');
});

it('expõe a regra de validação CpfCnpj', function () {
    expect(Validator::make(['doc' => '529.982.247-25'], ['doc' => [new CpfCnpj('cpf')]])->passes())->toBeTrue()
        ->and(Validator::make(['doc' => '529.982.247-25'], ['doc' => [new CpfCnpj('cnpj')]])->passes())->toBeFalse()
        ->and(Validator::make(['doc' => '000'], ['doc' => [new CpfCnpj]])->passes())->toBeFalse();
});
