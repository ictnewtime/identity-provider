<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPasswordExpiration
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Blocca se il campo è NULL (cambio forzato) OPPURE la data è passata
        if ($user && (is_null($user->password_expires_at) || now()->greaterThanOrEqualTo($user->password_expires_at))) {
            // Escludiamo le rotte di salvataggio/visualizzazione e il logout
            if (
                !$request->routeIs("password.expired") &&
                !$request->routeIs("password.force-update") &&
                !$request->routeIs("logout_web")
            ) {
                return redirect()->route("password.expired");
            }
        }

        return $next($request);
    }
}
