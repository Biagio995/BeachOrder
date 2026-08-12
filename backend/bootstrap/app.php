<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'tenant.route' => \App\Http\Middleware\SetTenantFromRoute::class,
            'tenant.user' => \App\Http\Middleware\SetTenantFromUser::class,
            'subscription.active' => \App\Http\Middleware\EnsureTenantSubscriptionActive::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\AssignRequestId::class,
            \App\Http\Middleware\LogRequestMetrics::class,
        ]);

        // API-only app: never call route('login') (missing) — that threw and rendered
        // a multi-second Ignition HTML page on every unauthenticated request.
        $middleware->redirectGuestsTo(fn () => rtrim((string) config('app.frontend_url'), '/').'/login');

        // Tenant context must be ready before implicit route-model binding,
        // otherwise BelongsToTenant scopes resolve to zero rows (404).
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetTenantFromUser::class,
        );
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetTenantFromRoute::class,
        );
    })
    ->withSchedule(function (Schedule $schedule) {
        if (! config('backup.enabled')) {
            return;
        }

        $backup = $schedule->command('backup:run')->withoutOverlapping();

        match (config('backup.frequency')) {
            'hourly' => $backup->hourly(),
            'weekly' => $backup->weekly(),
            default => $backup->daily(),
        };

        $schedule->command('backup:cleanup')->daily()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->report(function (\Throwable $e) {
            if (app()->environment('testing')) {
                return;
            }

            if (! app()->runningInConsole() && app()->bound('request')) {
                $request = app('request');
                if ($request->is('api/health', 'up', 'health')) {
                    return;
                }
            }

            \App\Services\Monitoring\CriticalErrorAlerter::alert(
                'unhandled_exception',
                'Unhandled application exception',
                [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => basename($e->getFile()).':'.$e->getLine(),
                ]
            );
        });
    })->create();
