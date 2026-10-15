<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Utilitário para valores monetários.
 *
 * Todo valor monetário do Nexus ERP é armazenado e calculado em CENTAVOS
 * inteiros (ex.: R$ 1.234,56 => 123456). Isso elimina os erros de
 * arredondamento de números de ponto flutuante (0.1 + 0.2 != 0.3).
 *
 * Multiplicações por quantidades fracionárias (ex.: 1,5 hora) usam a
 * extensão bcmath, que trabalha com strings decimais de precisão exata.
 */
final class Money
{
    /**
     * Formata centavos no padrão brasileiro: 123456 => "R$ 1.234,56".
     */
    public static function format(?int $cents, bool $withSymbol = true): string
    {
        $cents ??= 0;
        $negative = $cents < 0;
        $absolute = abs($cents);

        $integerPart = intdiv($absolute, 100);
        $decimalPart = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        $formatted = number_format($integerPart, 0, ',', '.').','.$decimalPart;

        if ($withSymbol) {
            $formatted = 'R$ '.$formatted;
        }

        return $negative ? '-'.$formatted : $formatted;
    }

    /**
     * Formata centavos para o valor exibido em campos de formulário: 123456 => "1.234,56".
     */
    public static function toInput(?int $cents): string
    {
        return $cents === null ? '' : self::format($cents, withSymbol: false);
    }

    /**
     * Converte um texto digitado pelo usuário em centavos, sem usar float.
     *
     * Aceita o formato brasileiro ("1.234,56", "R$ 1.234,56", "1234,5") e também
     * números simples com ponto decimal ("1234.56"), comuns em integrações.
     *
     * @throws InvalidArgumentException quando o texto não representa um valor válido.
     */
    public static function parse(string|int|null $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException('Valor monetário vazio.');
        }

        $value = str_replace(['R$', ' ', "\u{00A0}"], '', $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        if (str_contains($value, ',')) {
            // Formato brasileiro: pontos são separadores de milhar e a vírgula é decimal.
            $value = str_replace('.', '', $value);
            [$integer, $decimal] = array_pad(explode(',', $value, 2), 2, '0');
        } elseif (preg_match('/^\d+\.\d{1,2}$/', $value) === 1) {
            // Formato "1234.56": um único ponto seguido de 1 ou 2 casas decimais.
            [$integer, $decimal] = explode('.', $value, 2);
        } else {
            // Somente dígitos (com possíveis pontos de milhar): "1.234" => 1234 reais.
            $integer = str_replace('.', '', $value);
            $decimal = '0';
        }

        if (! ctype_digit($integer === '' ? '0' : $integer) || ! ctype_digit($decimal) || strlen($decimal) > 2) {
            throw new InvalidArgumentException("Valor monetário inválido: {$value}");
        }

        $cents = ((int) ($integer === '' ? '0' : $integer)) * 100 + (int) str_pad($decimal, 2, '0');

        return $negative ? -$cents : $cents;
    }

    /**
     * Versão tolerante de parse(): retorna null quando o valor é inválido.
     */
    public static function tryParse(string|int|null $value): ?int
    {
        try {
            return self::parse($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Multiplica um preço unitário (centavos) por uma quantidade decimal ("1.5"),
     * arredondando meio centavo para cima (ROUND_HALF_UP), sem float.
     */
    public static function multiply(int $unitCents, string $quantity): int
    {
        $quantity = self::normalizeDecimal($quantity);

        // Calcula com 4 casas de precisão e arredonda manualmente para inteiro.
        $product = bcmul((string) $unitCents, $quantity, 4);

        return self::roundHalfUp($product);
    }

    /**
     * Normaliza uma quantidade digitada ("1,5" ou "1.5") para o formato aceito pelo bcmath.
     */
    public static function normalizeDecimal(string|int $value): string
    {
        $value = str_replace(' ', '', (string) $value);

        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Quantidade inválida: {$value}");
        }

        return $value;
    }

    /**
     * Arredonda uma string decimal para o inteiro mais próximo (meio para cima).
     */
    private static function roundHalfUp(string $decimal): int
    {
        $isNegative = str_starts_with($decimal, '-');
        $absolute = ltrim($decimal, '-');
        $rounded = bcadd($absolute, '0.5', 0);

        return $isNegative ? -((int) $rounded) : (int) $rounded;
    }
}
