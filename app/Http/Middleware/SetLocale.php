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
        if (Session::has("locale")) {
            App::setLocale(Session::get("locale"));
        } else {
            $locale = $request->getPreferredLanguage(["it", "en"]) ?: "it";
            App::setLocale($locale);
        }

        return $next($request);
    }
}
