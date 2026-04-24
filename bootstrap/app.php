<?php

use App\Http\Middleware\EnsureCallCenterAdmin;
use App\Http\Middleware\EnsureCallCenterUser;
use App\Http\Middleware\EnsureRegionalBillingUser;
use App\Http\Middleware\EnsureUserIsAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\RunMasterIngestion::class,
        \App\Console\Commands\ScanUnused::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => ValidateCsrfToken::class,
        ]);

        $middleware->alias([
            'session.auth' => EnsureUserIsAuthenticated::class,
            'session.cc_user' => EnsureCallCenterUser::class,
            'session.rb_user' => EnsureRegionalBillingUser::class,
            'session.cc_admin' => EnsureCallCenterAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
