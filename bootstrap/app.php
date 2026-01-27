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


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // --- AGGIUNGI QUESTA SEZIONE 'then' ---
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/idp.php'));
        },
        // --------------------------------------
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'checkclientrole' => CheckClientRole::class,
            'localization' => Localization::class,
            'authenticated' => Authenticated::class,
            
            // 'guest' è nativo, ma forziamo il comportamento corretto
            'guest' => RedirectIfAuthenticated::class, 
            // 'guest' => \Illuminate\Auth\Middleware\Authenticate::class,
            
            // Colleghiamo il tuo vecchio 'web.authenticated' al middleware di Auth nativo
            'web.authenticated' => RedirectIfUnauthenticated::class,
            // 'web.authenticated' => \Illuminate\Auth\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
