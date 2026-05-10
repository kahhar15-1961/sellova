<?php

declare(strict_types=1);

use App\Http\Middleware\DispatchLegacyApi;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (filter_var(env('TRUST_PROXIES', false), FILTER_VALIDATE_BOOLEAN)) {
            $raw = (string) env('TRUSTED_PROXIES', '*');
            $at = str_contains($raw, ',')
                ? array_values(array_filter(array_map(trim(...), explode(',', $raw))))
                : $raw;
            $middleware->trustProxies(
                at: $at,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
                    | Request::HEADER_X_FORWARDED_AWS_ELB,
            );
        }

        $middleware->prepend(DispatchLegacyApi::class);
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin/dashboard');
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhooks/kyc/*',
        ]);
        $middleware->alias([
            'admin.panel' => \App\Http\Middleware\EnsureCanAccessAdminPanel::class,
            'admin.permission' => \App\Http\Middleware\EnsureAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
