<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Middleware personalizzati
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\Localization;
use App\Http\Middleware\Authenticated;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectIfUnauthenticated;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\EncryptCookies as CustomEncryptCookies;
use App\Http\Middleware\CheckClientCredentials;

// Middleware Core / Passport
use Illuminate\Cookie\Middleware\EncryptCookies as CoreEncryptCookies;
use Laravel\Passport\Http\Middleware\CheckScopes;
use Laravel\Passport\Http\Middleware\CheckForAnyScope;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . "/../routes/web.php",
        api: __DIR__ . "/../routes/api.php",
        commands: __DIR__ . "/../routes/console.php",
        health: "/up",
        // ------------------------------------------------------
        // then: function () {
        //     Route::middleware("web")
        //         // ->prefix('idp')
        //         ->group(base_path("routes/idp.php"));
        //     // Route::group(base_path('routes/idp.php'));
        //     // idp: __DIR__.'/../routes/idp.php',
        // },
        // ------------------------------------------------------
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 1. Esclusioni CSRF
        $middleware->validateCsrfTokens(except: ["/v2/login", "api/*"]);

        // 2. Sostituzione Middleware di Base (Il modo corretto in Laravel 11)
        $middleware->replace(CoreEncryptCookies::class, CustomEncryptCookies::class);
        // 3. Middleware aggiunti al gruppo "web"
        $middleware->web(
            append: [
                SetLocale::class, // TODO: debug locale. Da rimuovere
                HandleInertiaRequests::class,
            ],
        );

        // TODO debug locale . da rimuovere
        // $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);

        // 4. Alias (Nomi brevi per usare i middleware nelle rotte)
        $middleware->alias([
            // Autenticazione & Ruoli
            "guest" => RedirectIfAuthenticated::class,
            "web.authenticated" => RedirectIfUnauthenticated::class,
            "authenticated" => Authenticated::class,
            "role" => CheckRole::class,
            "client" => CheckClientCredentials::class,

            // Utility
            "localization" => Localization::class,

            // Passport
            "scopes" => CheckScopes::class,
            "scope" => CheckForAnyScope::class,
        ]);
        $middleware->web(append: [HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
