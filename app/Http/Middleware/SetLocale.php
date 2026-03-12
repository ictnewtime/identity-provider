<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Se c'è una lingua in sessione, dilla a Laravel
        if (Session::has("locale")) {
            App::setLocale(Session::get("locale"));
        }

        return $next($request);
    }
}
