<?php

namespace App\Support;

/**
 * Validação e formatação de CPF e CNPJ sem dependência de serviços externos.
 *
 * Os documentos são sempre armazenados "limpos" (sem pontos, barras e traços)
 * para que buscas e restrições UNIQUE funcionem independentemente da máscara
 * digitada pelo usuário.
 *
 * O CNPJ suporta o novo formato alfanumérico (IN RFB nº 2.229/2024, vigente a
 * partir de julho/2026): as 12 primeiras posições podem conter letras e os
 * dígitos verificadores são calculados usando o valor ASCII do caractere - 48.
 */
final class BrazilianDocument
{
    /**
     * Remove a máscara do documento: "123.456.789-09" => "12345678909".
     */
    public static function clean(?string $document): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $document) ?? '');
    }

    public static function isValidCpf(?string $cpf): bool
    {
        $cpf = self::clean($cpf);

        if (! preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($length = 9; $length < 11; $length++) {
            $sum = 0;

            for ($position = 0; $position < $length; $position++) {
                $sum += (int) $cpf[$position] * (($length + 1) - $position);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(?string $cnpj): bool
    {
        $cnpj = self::clean($cnpj);

        if (! preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $base = substr($cnpj, 0, 12);
        $firstDigit = self::cnpjCheckDigit($base);
        $secondDigit = self::cnpjCheckDigit($base.$firstDigit);

        return substr($cnpj, -2) === $firstDigit.$secondDigit;
    }

    /**
     * Valida automaticamente como CPF (11 caracteres) ou CNPJ (14 caracteres).
     */
    public static function isValid(?string $document): bool
    {
        $document = self::clean($document);

        return match (strlen($document)) {
            11 => self::isValidCpf($document),
            14 => self::isValidCnpj($document),
            default => false,
        };
    }

    /**
     * Aplica a máscara: CPF "000.000.000-00" ou CNPJ "00.000.000/0000-00".
     */
    public static function format(?string $document): string
    {
        $document = self::clean($document);

        return match (strlen($document)) {
            11 => preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $document),
            14 => preg_replace('/^(\w{2})(\w{3})(\w{3})(\w{4})(\w{2})$/', '$1.$2.$3/$4-$5', $document),
            default => $document,
        } ?? $document;
    }

    /**
     * Calcula um dígito verificador do CNPJ (numérico ou alfanumérico).
     */
    private static function cnpjCheckDigit(string $base): string
    {
        $weights = strlen($base) === 12
            ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
            : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;

        foreach (str_split($base) as $index => $character) {
            $sum += (ord($character) - 48) * $weights[$index];
        }

        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }

    /**
     * Gera um CPF válido (usado por factories/seeders de desenvolvimento).
     */
    public static function generateCpf(): string
    {
        do {
            $base = '';
            for ($i = 0; $i < 9; $i++) {
                $base .= random_int(0, 9);
            }

            for ($length = 9; $length < 11; $length++) {
                $sum = 0;
                for ($position = 0; $position < $length; $position++) {
                    $sum += (int) $base[$position] * (($length + 1) - $position);
                }
                $base .= ((10 * $sum) % 11) % 10;
            }
        } while (! self::isValidCpf($base));

        return $base;
    }

    /**
     * Gera um CNPJ numérico válido (usado por factories/seeders de desenvolvimento).
     */
    public static function generateCnpj(): string
    {
        do {
            $base = '';
            for ($i = 0; $i < 8; $i++) {
                $base .= random_int(0, 9);
            }
            $base .= '0001';

            $base .= self::cnpjCheckDigit($base);
            $base .= self::cnpjCheckDigit($base);
        } while (! self::isValidCnpj($base));

        return $base;
    }
}
