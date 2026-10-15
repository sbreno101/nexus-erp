<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Base dos controllers da API v1: paginação e ordenação padronizadas.
 *
 * Parâmetros aceitos em todas as listagens:
 *   ?per_page=1..100 (padrão 15) · ?page=N · ?sort=campo · ?direction=asc|desc · ?search=texto (nas listagens que têm busca; contas financeiras não têm)
 */
abstract class ApiController extends Controller
{
    protected function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 15)));
    }

    /**
     * Aplica ordenação somente em colunas permitidas (evita ORDER BY arbitrário).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $allowed
     * @return Builder<TModel>
     */
    protected function sort(Builder $query, Request $request, array $allowed, string $default, string $defaultDirection = 'asc'): Builder
    {
        $field = in_array($request->query('sort'), $allowed, true) ? $request->query('sort') : $default;
        $direction = $request->query('sort') !== null
            ? ($request->query('direction') === 'desc' ? 'desc' : 'asc')
            : $defaultDirection;

        return $query->orderBy($query->qualifyColumn($field), $direction)->orderBy($query->qualifyColumn('id'));
    }
}
