<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cabeçalhos de segurança em todas as respostas (web e API).
        $middleware->append(SecurityHeaders::class);

        // Executados em todas as rotas web (incluindo as requisições do Livewire).
        $middleware->web(append: [
            EnsureUserIsActive::class,
            SetCurrentCompany::class,
        ]);

        $middleware->api(append: [
            EnsureUserIsActive::class,
            SetCurrentCompany::class,
        ]);

        // A empresa ativa precisa ser definida ANTES do Route Model Binding,
        // para que o CompanyScope filtre os models resolvidos pela URL.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentCompany::class);
        $middleware->prependToPriorityList(before: SetCurrentCompany::class, prepend: EnsureUserIsActive::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Violações de regra de negócio são erros esperados do usuário, não falhas do sistema:
        // não poluem o log de erros.
        $exceptions->dontReport(BusinessRuleException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Formato de erro consistente na API: {"message": "..."} em português.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Não autenticado. Envie um token válido no cabeçalho Authorization: Bearer <token>.'], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                $message = $exception->getPrevious() instanceof ModelNotFoundException
                    ? 'Registro não encontrado.'
                    : 'Recurso não encontrado.';

                return response()->json(['message' => $message], 404);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                $message = $exception->getPrevious() instanceof AuthorizationException
                    ? 'Você não tem permissão para executar esta ação.'
                    : ($exception->getMessage() ?: 'Acesso negado.');

                return response()->json(['message' => $message], 403);
            }
        });

        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($request->is('api/*') && $exception->getStatusCode() === 405) {
                return response()->json(['message' => 'Método HTTP não permitido para este endpoint.'], 405);
            }
        });
    })->create();
