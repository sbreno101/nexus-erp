<?php

namespace App\Http\Requests\Concerns;

use App\Support\Money;

/**
 * Converte campos monetários digitados ("1.234,56") em centavos inteiros
 * ANTES da validação (prepareForValidation).
 *
 * - Tela web: envia "amount" (texto com máscara) => vira "amount_cents".
 * - API: pode enviar diretamente "amount_cents" (inteiro) — nada é convertido.
 *
 * Assim o MESMO Form Request (e as mesmas regras) atende web e API.
 * Valores inválidos viram a string "invalid", reprovada pela regra "integer".
 */
trait NormalizesMoney
{
    /**
     * Mapa "campo do formulário" => "campo em centavos".
     *
     * @return array<string, string>
     */
    abstract protected function moneyFields(): array;

    protected function normalizeMoneyFields(): void
    {
        $normalized = [];

        foreach ($this->moneyFields() as $input => $target) {
            if (! $this->exists($input)) {
                continue;
            }

            $normalized[$target] = $this->filled($input)
                ? (Money::tryParse((string) $this->input($input)) ?? 'invalid')
                : null;
        }

        $this->merge($normalized);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMoneyFields();
    }
}
