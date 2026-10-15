<?php

namespace App\Exceptions;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Violação de uma regra de negócio (ex.: "proposta já convertida",
 * "valor maior que o saldo do título").
 *
 * As Actions lançam esta exceção; o Laravel chama render() automaticamente:
 *   - Web: volta para a página anterior com a mensagem de erro.
 *   - API: responde 422 em JSON, no mesmo formato dos erros de validação.
 */
class BusinessRuleException extends DomainException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => ['business_rule' => [$this->getMessage()]],
            ], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
