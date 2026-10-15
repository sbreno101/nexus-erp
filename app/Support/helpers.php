<?php

use App\Support\BrazilianDocument;
use App\Support\Money;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Helpers globais de apresentação
|--------------------------------------------------------------------------
| Funções curtas usadas principalmente nas views Blade. Carregadas pelo
| Composer (chave "autoload.files" do composer.json).
*/

if (! function_exists('money')) {
    /**
     * Formata centavos como moeda brasileira: money(123456) => "R$ 1.234,56".
     */
    function money(?int $cents, bool $withSymbol = true): string
    {
        return Money::format($cents, $withSymbol);
    }
}

if (! function_exists('document_format')) {
    /**
     * Aplica máscara de CPF/CNPJ.
     */
    function document_format(?string $document): string
    {
        return BrazilianDocument::format($document);
    }
}

if (! function_exists('br_date')) {
    /**
     * Formata datas no padrão brasileiro (dd/mm/aaaa ou dd/mm/aaaa hh:mm).
     */
    function br_date(Carbon|DateTimeInterface|string|null $date, bool $withTime = false): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $date = $date instanceof DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);

        return $date->timezone(config('app.timezone'))->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    }
}
