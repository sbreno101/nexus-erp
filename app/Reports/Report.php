<?php

namespace App\Reports;

use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Base de todos os relatórios do Nexus ERP.
 *
 * Cada relatório é uma classe pequena que DESCREVE:
 *  - quem pode vê-lo (permission) e onde aparece (group);
 *  - quais filtros aceita (filters) e quais colunas exibe (columns);
 *  - como buscar os dados (rows), opcionalmente totais e cartões de resumo.
 *
 * O Controller e as views são genéricos: sabem desenhar QUALQUER relatório a
 * partir dessa descrição. Assim, criar um relatório novo é só criar uma classe
 * e registrá-la no ReportRegistry — sem tela nova, sem rota nova.
 *
 * Convenção dos valores devolvidos em rows()/totals() (por tipo de coluna):
 *  - text: string | null
 *  - money: inteiro em CENTAVOS (a formatação fica para a view/exportação)
 *  - date/datetime: Carbon, DateTimeInterface ou string "Y-m-d"
 *  - number: int | float
 *  - percent: float de 0 a 100 | null (null = "não se aplica")
 *  - badge: array{label: string, color: string}
 *
 * Os models usam o trait BelongsToCompany, então TODA consulta Eloquent feita
 * aqui já vem filtrada pela empresa ativa — não é preciso filtrar company_id.
 */
abstract class Report
{
    /**
     * Cache das definições de filtros (alguns filtros consultam o banco para
     * montar as opções; assim a consulta roda uma única vez por requisição).
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $resolvedFilters = null;

    /**
     * Resultados memorizados por remember() (ex.: agregados usados tanto
     * nos totais quanto nos cartões de resumo).
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * Identificador usado na URL (/relatorios/{key}).
     */
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Agrupamento na tela inicial: "Comercial", "Financeiro" ou "Operacional".
     */
    abstract public function group(): string;

    /**
     * Permissão exigida: reports.commercial | reports.financial | reports.operational.
     */
    abstract public function permission(): string;

    /**
     * Colunas da tabela, na ordem de exibição.
     *
     * @return array<string, array{label: string, type: string, align?: string, decimals?: int}>
     */
    abstract public function columns(): array;

    /**
     * Linhas do relatório (cada linha é "chave da coluna => valor bruto").
     * Pode devolver um array ou um generator (para grandes volumes, um
     * generator com ->lazy() evita carregar tudo na memória de uma vez).
     *
     * @param  array<string, mixed>  $filters  filtros JÁ normalizados (normalizeFilters)
     * @return iterable<array<string, mixed>>
     */
    abstract public function rows(array $filters): iterable;

    /**
     * Ícone exibido no cartão do relatório (nomes do componente <x-icon>).
     */
    public function icon(): string
    {
        return 'chart-bar';
    }

    /**
     * Definição dos filtros exibidos no formulário.
     *
     * Formato de cada item ("nome do parâmetro na URL" => definição):
     *  - type: "date" | "select" | "text"
     *  - label: rótulo do campo
     *  - options: (select) array "valor => rótulo"
     *  - placeholder: (select) rótulo da opção vazia ("Todos"); sem ele não há opção vazia
     *  - default: valor usado quando o parâmetro não vem ou é inválido
     *
     * @return array<string, array{type: string, label: string, options?: array<int|string, string>, placeholder?: string, default?: string|null}>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Linha de totais (mesmas chaves das colunas). A chave especial "_label"
     * substitui o texto "Total" exibido na primeira coluna.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function totals(array $filters): array
    {
        return [];
    }

    /**
     * Cartões de resumo exibidos acima da tabela (valores JÁ formatados).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, value: string, icon?: string, color?: string, hint?: string|null}>
     */
    public function summary(array $filters): array
    {
        return [];
    }

    /**
     * Versão memorizada de filters() — use esta nas views e no controller.
     *
     * @return array<string, array<string, mixed>>
     */
    public function filterDefinitions(): array
    {
        return $this->resolvedFilters ??= $this->filters();
    }

    /**
     * Valida e normaliza os filtros vindos da query string.
     *
     * Nunca confiamos no que vem da URL: datas inválidas voltam ao padrão,
     * opções que não existem na lista são ignoradas e textos são aparados.
     * Se o período vier invertido (de > até), as datas são trocadas.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    public function normalizeFilters(array $input): array
    {
        $normalized = [];

        foreach ($this->filterDefinitions() as $name => $filter) {
            $raw = $input[$name] ?? null;
            $default = $filter['default'] ?? null;

            $normalized[$name] = match ($filter['type']) {
                'date' => $this->normalizeDate($raw, $default),
                'select' => $this->normalizeSelect($raw, $filter, $default),
                default => is_string($raw) && trim($raw) !== '' ? Str::limit(trim($raw), 100, '') : $default,
            };
        }

        if (! empty($normalized['de']) && ! empty($normalized['ate']) && $normalized['de'] > $normalized['ate']) {
            [$normalized['de'], $normalized['ate']] = [$normalized['ate'], $normalized['de']];
        }

        return $normalized;
    }

    /**
     * Filtros preenchidos em formato legível ("rótulo => valor"), usado no PDF.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function describeFilters(array $filters): array
    {
        $described = [];

        foreach ($this->filterDefinitions() as $name => $filter) {
            $value = $filters[$name] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $described[$filter['label']] = match ($filter['type']) {
                'date' => br_date($value),
                'select' => (string) ($filter['options'][$value] ?? $value),
                default => (string) $value,
            };
        }

        return $described;
    }

    /**
     * Formata um valor para exibição (tela e PDF), conforme o tipo da coluna.
     *
     * @param  array{type: string, decimals?: int}  $column
     */
    public function formatValue(array $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return $column['type'] === 'money' ? money(0) : '—';
        }

        return match ($column['type']) {
            'money' => money((int) $value),
            'date' => br_date($value),
            'datetime' => br_date($value, withTime: true),
            'number' => number_format((float) $value, $column['decimals'] ?? 0, ',', '.'),
            'percent' => number_format((float) $value, $column['decimals'] ?? 1, ',', '.').'%',
            'badge' => (string) ($value['label'] ?? ''),
            default => $value instanceof BackedEnum ? (string) $value->value : (string) $value,
        };
    }

    /**
     * Formata um valor para o CSV: números no padrão brasileiro SEM separador
     * de milhar e sem símbolo ("1234,56"), para o Excel reconhecer como número.
     *
     * @param  array{type: string, decimals?: int}  $column
     */
    public function csvValue(array $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($column['type']) {
            'money' => self::centsToDecimal((int) $value),
            'date' => $this->toCarbon($value)->format('d/m/Y'),
            'datetime' => $this->toCarbon($value)->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'number' => number_format((float) $value, $column['decimals'] ?? 0, ',', ''),
            'percent' => number_format((float) $value, $column['decimals'] ?? 1, ',', ''),
            'badge' => (string) ($value['label'] ?? ''),
            default => (string) $value,
        };
    }

    /**
     * Alinhamento da coluna: números à direita (facilita comparar valores).
     *
     * @param  array{type: string, align?: string}  $column
     */
    public function alignment(array $column): string
    {
        return $column['align'] ?? (in_array($column['type'], ['money', 'number', 'percent'], true) ? 'right' : 'left');
    }

    /**
     * Executa o cálculo uma única vez por combinação de filtros.
     *
     * Útil quando totals() e summary() dependem das mesmas consultas
     * agregadas: a segunda chamada reaproveita o resultado da primeira.
     *
     * @template TValue
     *
     * @param  array<string, mixed>  $filters
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    protected function remember(string $name, array $filters, Closure $callback): mixed
    {
        $key = $name.'|'.md5(serialize($filters));

        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $callback();
        }

        return $this->memo[$key];
    }

    /**
     * Converte centavos em "1234,56" sem usar float (evita erros de arredondamento).
     */
    protected static function centsToDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).','.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Lista de valores de enum pronta para um "IN (...)" em SQL bruto.
     *
     * É seguro interpolar porque os valores vêm de constantes do código
     * (enums), nunca de dados digitados pelo usuário.
     *
     * @param  list<BackedEnum>  $cases
     */
    protected static function sqlList(array $cases): string
    {
        return implode(', ', array_map(fn (BackedEnum $case): string => "'".$case->value."'", $cases));
    }

    /**
     * Nomes dos usuários (responsáveis/atendentes) em UMA consulta — evita N+1.
     *
     * @param  iterable<int|null>  $ids
     * @return array<int, string>
     */
    protected function userNames(iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /**
     * Data inicial/final do período como CarbonImmutable (início/fim do dia).
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    protected function period(array $filters): array
    {
        return [
            empty($filters['de']) ? null : CarbonImmutable::parse($filters['de'])->startOfDay(),
            empty($filters['ate']) ? null : CarbonImmutable::parse($filters['ate'])->endOfDay(),
        ];
    }

    private function normalizeDate(mixed $raw, ?string $default): ?string
    {
        if (! is_string($raw) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return $default;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $raw);
        } catch (Throwable) {
            return $default;
        }

        // createFromFormat aceita "2026-02-31" (vira 03/03): comparamos para rejeitar.
        return $date !== null && $date->format('Y-m-d') === $raw ? $raw : $default;
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function normalizeSelect(mixed $raw, array $filter, ?string $default): ?string
    {
        if ($raw === null || ! is_scalar($raw)) {
            return $default;
        }

        $raw = (string) $raw;

        if ($raw === '') {
            return array_key_exists('placeholder', $filter) ? '' : $default;
        }

        return array_key_exists($raw, $filter['options'] ?? []) ? $raw : $default;
    }

    private function toCarbon(mixed $value): Carbon
    {
        return $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);
    }
}
