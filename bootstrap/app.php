<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckClientRole;
use App\Http\Middleware\Localization;
use App\Http\Middleware\Authenticated;
// use App\Http\Middleware\old\RedirectIfAuthenticated as RedirectIfAuthenticated2;
// use App\Http\Middleware\old\RedirectIfUnauthenticated as RedirectIfUnauthenticated2;


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
            // 'guest' => RedirectIfAuthenticated2::class, 
            // 'guest' => \Illuminate\Auth\Middleware\Authenticate::class,
            'guest' => true,
            
            // Colleghiamo il tuo vecchio 'web.authenticated' al middleware di Auth nativo
            // 'web.authenticated' => RedirectIfUnauthenticated2::class,
            // 'web.authenticated' => \Illuminate\Auth\Middleware\Authenticate::class,
            'web.authenticated' => true,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
