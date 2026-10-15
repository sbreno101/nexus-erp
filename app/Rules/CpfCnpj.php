<?php

namespace App\Rules;

use App\Support\BrazilianDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regra de validação para CPF e/ou CNPJ.
 *
 * Uso: ['document' => ['required', new CpfCnpj]]         // aceita ambos
 *      ['cnpj' => ['required', new CpfCnpj('cnpj')]]     // somente CNPJ
 */
class CpfCnpj implements ValidationRule
{
    /**
     * @param  'cpf'|'cnpj'|null  $only
     */
    public function __construct(private ?string $only = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isValid = match ($this->only) {
            'cpf' => BrazilianDocument::isValidCpf($value),
            'cnpj' => BrazilianDocument::isValidCnpj($value),
            default => BrazilianDocument::isValid($value),
        };

        if (! $isValid) {
            $fail(match ($this->only) {
                'cpf' => 'O campo :attribute não contém um CPF válido.',
                'cnpj' => 'O campo :attribute não contém um CNPJ válido.',
                default => 'O campo :attribute não contém um CPF ou CNPJ válido.',
            });
        }
    }
}
