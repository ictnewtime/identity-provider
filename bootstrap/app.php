<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckClientRole;
use App\Http\Middleware\Localization;
use App\Http\Middleware\Authenticated;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectIfUnauthenticated;
// use App\Http\Middleware\CheckClientCredentials;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . "/../routes/web.php",
        api: __DIR__ . "/../routes/api.php",
        commands: __DIR__ . "/../routes/console.php",
        health: "/up",
        // --- AGGIUNGI QUESTA SEZIONE 'then' ---
        // then: function () {
        //     Route::middleware("web")
        //         // ->prefix('idp')
        //         ->group(base_path("routes/idp.php"));
        //     // Route::group(base_path('routes/idp.php'));
        //     // idp: __DIR__.'/../routes/idp.php',
        // },
        // --------------------------------------
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ["v2/login", "api/*"]);
        $middleware->alias([
            "role" => CheckRole::class,
            "checkclientrole" => CheckClientRole::class,
            "localization" => Localization::class,
            "authenticated" => Authenticated::class,

            "guest" => RedirectIfAuthenticated::class,
            "web.authenticated" => RedirectIfUnauthenticated::class,
            "client" => CheckClientCredentials::class,

            "scopes" => \Laravel\Passport\Http\Middleware\CheckScopes::class,
            "scope" => \Laravel\Passport\Http\Middleware\CheckForAnyScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
