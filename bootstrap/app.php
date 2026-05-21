<?php

use App\Http\Middleware\EnsureUserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login.show'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiRequest = fn (Request $request): bool => $request->expectsJson()
            || $request->is('api/*')
            || $request->is('api/v1/*');

        $jsonError = fn (string $message, int $status, array $extra = []) => response()->json([
            'message' => $message,
            ...$extra,
            'status' => $status,
        ], $status);

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $jsonError('Unauthenticated.', SymfonyResponse::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $jsonError('Forbidden.', SymfonyResponse::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $jsonError('The given data was invalid.', SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY, [
                'errors' => $exception->errors(),
            ]);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $jsonError('Resource not found.', SymfonyResponse::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $jsonError('Method not allowed.', SymfonyResponse::HTTP_METHOD_NOT_ALLOWED);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $message = $request->route() === null ? 'Endpoint not found.' : 'Resource not found.';

            return $jsonError($message, SymfonyResponse::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = match ($status) {
                SymfonyResponse::HTTP_FORBIDDEN => 'Forbidden.',
                SymfonyResponse::HTTP_NOT_FOUND => $request->route() === null ? 'Endpoint not found.' : 'Resource not found.',
                SymfonyResponse::HTTP_METHOD_NOT_ALLOWED => 'Method not allowed.',
                default => $exception->getMessage() ?: (SymfonyResponse::$statusTexts[$status] ?? 'Something went wrong.'),
            };

            return $jsonError($message, $status);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $jsonError('Something went wrong.', SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
