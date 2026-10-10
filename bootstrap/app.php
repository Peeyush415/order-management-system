<?php

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The SPA is served from the same origin, so it authenticates with the session cookie (Sanctum).
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $wantsJson = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($wantsJson);

        // Every API error uses the same envelope: { success: false, message, errors? }.
        // Laravel has already turned ModelNotFound into a 404 and AuthorizationException into a 403 HttpException here.
        $exceptions->render(function (Throwable $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error($e->getMessage(), $e->status, $e->errors()),
                $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
                $e instanceof BusinessException => ApiResponse::error($e->getMessage(), $e->status(), $e->errors()),
                $e instanceof NotFoundHttpException => ApiResponse::error('Resource not found.', 404),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() ?: (Response::$statusTexts[$e->getStatusCode()] ?? 'Error'),
                    $e->getStatusCode(),
                )->withHeaders($e->getHeaders()),
                // Unexpected errors: the details go to the log (reporting is separate), never to the client.
                default => ApiResponse::error('Something went wrong. Please try again later.', 500),
            };
        });
    })->create();
