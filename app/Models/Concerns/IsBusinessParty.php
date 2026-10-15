<?php

namespace App\Models\Concerns;

use App\Support\BrazilianDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Comportamentos comuns a clientes e fornecedores ("partes" de negócio):
 * busca textual, documento formatado e nome de exibição.
 *
 * @mixin Model
 */
trait IsBusinessParty
{
    /**
     * Busca por nome, nome fantasia, e-mail ou CPF/CNPJ (com ou sem máscara).
     *
     * ILIKE é o "LIKE sem diferenciar maiúsculas" do PostgreSQL. Os valores
     * são enviados como parâmetros (bindings), o que impede SQL Injection.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $document = BrazilianDocument::clean($term);

        $query->where(function (Builder $query) use ($term, $document): void {
            $query->whereAny(['name', 'trade_name', 'email'], 'ilike', "%{$term}%");

            if (strlen($document) >= 3) {
                $query->orWhere('document', 'like', "%{$document}%");
            }
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedDocument(): Attribute
    {
        return Attribute::get(fn (): string => BrazilianDocument::format($this->document));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->trade_name ?: $this->name);
    }

    /**
     * Garante que o documento seja sempre gravado sem máscara.
     *
     * @return Attribute<string, string>
     */
    protected function document(): Attribute
    {
        return Attribute::set(fn (?string $value): string => BrazilianDocument::clean($value));
    }
}
