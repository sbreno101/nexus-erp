<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos HTTP de segurança aplicados a todas as respostas.
 *
 *  - X-Frame-Options: impede que o sistema seja carregado dentro de um
 *    <iframe> de outro site (proteção contra clickjacking).
 *  - X-Content-Type-Options: o navegador não "adivinha" o tipo do arquivo
 *    (evita executar um upload como script).
 *  - Referrer-Policy: não vaza URLs internas (com IDs) para sites externos.
 *  - Permissions-Policy: desliga recursos do navegador que o ERP não usa.
 *  - Strict-Transport-Security: em produção com HTTPS, força HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
