<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Middleware personalizzati
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\Localization;
use App\Http\Middleware\Authenticated;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\EncryptCookies as CustomEncryptCookies;
// use App\Http\Middleware\CheckClientCredentials;
use App\Http\Middleware\ProviderClientCredentials;
use App\Http\Middleware\VerifyExternalToken;
use App\Http\Middleware\CheckPasswordExpiration;

// Middleware Core / Passport
use Illuminate\Cookie\Middleware\EncryptCookies as CoreEncryptCookies;
use Illuminate\Http\Request;
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
        $middleware->validateCsrfTokens(
            except: ["/v2/login", "api/*", "admin/v1/*", "logout", "password/force-update"],
        );

        // 2. Sostituzione Middleware di Base (Il modo corretto in Laravel 11)
        $middleware->replace(CoreEncryptCookies::class, CustomEncryptCookies::class);
        // 3. Middleware aggiunti al gruppo "web"
        $middleware->web(
            append: [
                SetLocale::class, // TODO: debug locale. Da rimuovere
                HandleInertiaRequests::class,
            ],
        );

        $middleware->web(append: [SetLocale::class]);

        // 4. Alias (Nomi brevi per usare i middleware nelle rotte)
        $middleware->alias([
            // Autenticazione & Ruoli
            "guest" => RedirectIfAuthenticated::class,
            // "web.authenticated" => RedirectIfUnauthenticated::class,
            "authenticated" => Authenticated::class,
            "role" => CheckRole::class,
            "verify_external_token" => VerifyExternalToken::class,
            "password.expiration" => CheckPasswordExpiration::class,

            // Utility
            "localization" => Localization::class,

            // Passport
            // "client" => CheckClientCredentials::class,
            "client" => ProviderClientCredentials::class,
            "scopes" => CheckScopes::class,
            "scope" => CheckForAnyScope::class,
        ]);
        $middleware->web(append: [HandleInertiaRequests::class]);
        // CATTURIAMO I PARAMETRI SSO PRIMA DEL REDIRECT DI DEFAULT!
        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            // Se l'utente è già loggato e arriva con una richiesta per un'app esterna
            if ($request->has("provider_id")) {
                $request->session()->put("pending_sso_provider_id", $request->input("provider_id"));
                $request->session()->put("pending_sso_redirect_to", $request->input("redirect_to"));
            }

            // Il redirect di default per gli utenti loggati (cambialo con la tua rotta home)
            return "/admin-home";
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            if ($request->is("api/*")) {
                return true;
            }
            return $request->expectsJson();
        });
    })
    ->create();
