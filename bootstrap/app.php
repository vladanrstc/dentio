<?php

use App\Exceptions\AppointmentConflictException;
use App\Exceptions\InviteActionNotAllowedException;
use App\Exceptions\InviteResendNotAllowedException;
use App\Exceptions\MissingCompanyContextException;
use App\Exceptions\PaymentActionNotAllowedException;
use App\Exceptions\ReportExportException;
use App\Exceptions\StripeConfigurationException;
use App\Exceptions\StripeWebhookException;
use App\Exceptions\TenantResourceNotFoundException;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reports:send-due')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AppointmentConflictException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'assigned_user_id' => [$exception->getMessage()],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (InviteActionNotAllowedException|MissingCompanyContextException|PaymentActionNotAllowedException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (StripeWebhookException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        });

        $exceptions->render(function (StripeConfigurationException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (ReportExportException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (InviteResendNotAllowedException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'invite' => [$exception->getMessage()],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (TenantResourceNotFoundException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        });
    })->create();
